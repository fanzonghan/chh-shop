<?php


namespace app\jobs;

use app\dao\order\StoreOrderDao;
use app\model\AwardLog;
use app\model\user\User;
use app\model\user\UserBrokerage;
use app\model\user\UserMoney;
use app\services\user\UserBillServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;

/**
 * Class DailyAwardJob
 * @package app\jobs
 * @author xiaofan
 * @date 2026/2/2
 * 神兽保佑 永无bug
 */
class DailyAwardJob extends BaseJobs
{
    use QueueTrait;

    protected $logs = [];
    /**
     * 每日结算次日奖励
     * @return bool
     */
    public function doJob()
    {
        //当日总业绩 已支付未退款的订单
        //销售额
        /** @var StoreOrderDao $StoreOrderDao */
        $StoreOrderDao = app()->make(StoreOrderDao::class);
        $today_sales = $StoreOrderDao->todaySales('yesterday');
        $this->logs[] = '昨日销售额：'.$today_sales;
        //时间
        $day = date('Y-m-d',strtotime('-1 day'));

        $averageBonus = true;
        if($averageBonus){
            //平台每日总业绩10%平均分配给会员
            $userList = User::where('spread_open', 1)->where('is_promoter',1)->where('integral','>',0)->field('uid,nickname,integral,agent_level,user_type')->select();
            $userCount = count($userList);
            $this->logs[] = "达标用户数量：$userCount";
            if($userCount > 0){
                //拿出业绩的10%
                $com_bonus = bcmul($today_sales, 0.1,2);
                //平均分配
                $bonus = bcdiv($com_bonus, $userCount,2);

                $this->logs[] = "总业绩10%($com_bonus)平均分配：$bonus";
                foreach ($userList as $userInfo){
                    $this->sendBonus($userInfo,$bonus,$day.'收益');
                }
            }
        }

        $weightBonus = true;
        if($weightBonus){
            //平台每日总业绩3%平均分配给9-10星会员
            $userList = User::where('spread_open', 1)->where('is_promoter',1)->where('integral','>',0)->whereIn('agent_level', [9,10])->field('uid,nickname,integral,agent_level,user_type')->select();
            $userCount = count($userList);
            $this->logs[] = "9-10星会员数量：$userCount";
            if($userCount > 0) {
                //拿出业绩的3%
                $com_bonus = bcmul($today_sales, 0.03,2);
                //平均分配
                $bonus = bcdiv($com_bonus, $userCount,2);
                $this->logs[] = "总业绩3%($com_bonus)平均分配：$bonus";

                foreach ($userList as $userInfo){
                    $this->sendBonus($userInfo,$bonus,$day.'加权收益');
                }
            }
        }
        $this->logs[] = "执行完毕";

        AwardLog::create([
            'title'=>$day.'奖励发放日志',
            'day'=>$day,
            'logs'=>json_encode($this->logs,JSON_UNESCAPED_UNICODE)
        ]);
        return true;
    }

    protected function sendBonus($userInfo,$bonus,$title){
        //bcadd: 加法,bcsub: 减法,bcmul: 乘法,bcdiv: 除法
        $this->logs[] = "用户：{$userInfo['uid']}-{$userInfo['nickname']}，$title";
        //如果用户的权益值小于收益上限
        $is_sx = false;
        if($userInfo['integral'] < $bonus){
            $bonus = $userInfo['integral'];//按照最大上限获取
            $is_sx = true;
            $this->logs[] = "用户收益达到上限，按照权益值上限获取：$bonus";
        }else{
            $this->logs[] = "用户权益值充足，按照最大上限获取：$bonus";
        }
        //1-4星会员 每笔收益30%进余额 70%进佣金
        if(in_array($userInfo['agent_level'],[0,1,2,3,4])){
            $yue_bonus = bcmul($bonus,0.3,2);
            $yong_bonus = bcmul($bonus,0.7,2);
            $this->logs[] = "1-4星会员：Y：$yue_bonus B：$yong_bonus";
        }
        //5-10星会员 每笔收益40%进余额 60%进佣金
        if(in_array($userInfo['agent_level'],[5,6,7,8,9,10])){
            $yue_bonus = bcmul($bonus,0.4,2);
            $yong_bonus = bcmul($bonus,0.6,2);
            $this->logs[] = "5-10星会员：Y：$yue_bonus B：$yong_bonus";
        }
        if(isset($yue_bonus) && isset($yong_bonus)){
            //增加佣金
            $brokerage_price = bcadd($userInfo['brokerage_price'], $yong_bonus,2);
            $this->saveBrokerage($userInfo['uid'],$yong_bonus,$brokerage_price,'today_bonus',1,$title,'收益70%进佣金');
            $userInfo->brokerage_price = $brokerage_price;

            //增加余额
            $now_money = bcadd($userInfo['now_money'], $yue_bonus,2);
            $this->saveMoneyBill($userInfo['uid'], $yue_bonus, $now_money,'today_bonus',1,$title,'收益30%进余额');
            $userInfo->now_money = $now_money;

            //扣除权益值
            $integral = bcsub($userInfo['integral'], $bonus,2);//剩余权益值
            $this->saveIntegral($userInfo['uid'], $bonus, $integral,'权益值释放'.($is_sx?'（达到上限）':''));
            $userInfo->integral = $integral;
            $userInfo->save();

            //提醒推送
            event('NoticeListener', [['spread_uid' => $userInfo['uid'], 'userType' => $userInfo['user_type'], 'brokeragePrice' => $bonus, 'goodsName' => $title.'到账', 'goodsPrice' => $bonus, 'add_time' => time()], 'order_brokerage']);

        }
    }

    protected function saveBrokerage($uid,$number,$balance,$type,$pm=1,$title='',$mark=''){
        //佣金冻结时间
        $broken_time = intval(sys_config('extract_time'));
        $frozen_time = time() + $broken_time * 86400;
        $data = [
            'uid'=>$uid,
            'link_id'=>0,
            'type'=>$type,
            'title'=>$title,
            'mark'=>$mark,
            'number'=>$number,
            'balance'=>$balance,
            'pm'=>$pm,
            'status'=>1,
            'frozen_time'=>$frozen_time,
            'add_time'=>time(),
        ];
        UserBrokerage::create($data);
    }

    protected function saveMoneyBill($uid,$number,$balance,$type,$pm=1,$title='',$mark=''){
        //写入余额记录
        $data = [
            'uid'=>$uid,
            'link_id'=>0,
            'type'=>$type,
            'title'=>$title,
            'mark'=>$mark,
            'number'=>$number,
            'balance'=>$balance,
            'pm'=>$pm,
            'status'=>1,
            'add_time'=>time(),
        ];
        UserMoney::create($data);
    }
    /**
     * 写入积分记录
     * @param $uid int 用户uid
     * @param $number float 数量
     * @param $balance float 剩余数量
     */
    protected function saveIntegral($uid,$number,$balance,$title){
        /** @var UserBillServices $userBillServices */
        $userBillServices = app()->make(UserBillServices::class);
        $integral_data = [
            'link_id' => 0,
            'number' => $number,
            'balance'=>$balance,
            'title'=>$title,
            'mark'=>'系统释放了' . floatval($number) . '权益值'
        ];
        $userBillServices->expendIntegral($uid, 'system_sub', $integral_data);
    }

}