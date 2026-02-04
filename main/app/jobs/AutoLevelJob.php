<?php


namespace app\jobs;

use app\model\user\User;
use app\model\user\UserAreaBind;
use app\model\user\UserUpBind;
use app\services\order\StoreOrderServices;
use app\services\user\UserServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * Class AutoLevelJob
 * @package app\jobs
 * @author xiaofan
 * @date 2026/2/2
 * 神兽保佑 永无bug
 */
class AutoLevelJob extends BaseJobs
{
    use QueueTrait;

    /**
     * @return bool
     */
    public function doJob()
    {
        //找出平台全部已激活的会员（有推广权限）
        $userList = User::where('spread_open', 1)->where('is_promoter',1)->field('uid,spread_open,agent_level')->select();
        Log::error(json_encode($userList,256));

        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        foreach ($userList as $user) {
            Log::error($user['uid'].':');
            //找出用户的左右区
            $LUid = UserAreaBind::where('uid',$user['uid'])->where('area_label','L')->value('zid');
            $RUid = UserAreaBind::where('uid',$user['uid'])->where('area_label','R')->value('zid');
            if($LUid && $RUid){
                //两个区都存在
                $Lmoney = User::where('uid',$LUid)->value('taem_consume');
                Log::error('L:'.$Lmoney);
                $Rmoney = User::where('uid',$RUid)->value('taem_consume');
                Log::error('r:'.$Rmoney);
                //找出最小值
                $min = min($Lmoney,$Rmoney);
                Log::error('min:'.$min);

                //达到1.5亿升级 agent_level = 10
                if($min >= 150000000 && $user['agent_level'] < 10){
                    User::update(['agent_level' => 10],['uid' => $user['uid']]);
                }
                //达到5000万升级 agent_level = 9
                else if($min >= 50000000 && $user['agent_level'] < 9){
                    User::update(['agent_level' => 9],['uid' => $user['uid']]);
                }
                //达到1500万升级 agent_level = 8
                else if($min >= 15000000 && $user['agent_level'] < 8){
                    User::update(['agent_level' => 8],['uid' => $user['uid']]);
                }
                //达到500万升级 agent_level = 7
                else if($min >= 5000000 && $user['agent_level'] < 7){
                    User::update(['agent_level' => 7],['uid' => $user['uid']]);
                }
                //达到200万升级 agent_level = 6
                else if($min >= 2000000 && $user['agent_level'] < 6){
                    User::update(['agent_level' => 6],['uid' => $user['uid']]);
                }
                //达到50万升级 agent_level = 5
                else if($min >= 500000 && $user['agent_level'] < 5){
                    User::update(['agent_level' => 5],['uid' => $user['uid']]);
                }
                //达到20万升级 agent_level = 4
                else if($min >= 200000 && $user['agent_level'] < 4){
                    User::update(['agent_level' => 4],['uid' => $user['uid']]);
                }
                //达到5万升级 agent_level = 3
                else if($min >= 50000 && $user['agent_level'] < 3){
                    User::update(['agent_level' => 3],['uid' => $user['uid']]);
                }
                //达到2万升级 agent_level = 2
                else if($min >= 20000 && $user['agent_level'] < 2){
                    User::update(['agent_level' => 2],['uid' => $user['uid']]);
                }
                //达到6千升级 agent_level = 1
                else if($min >= 6000 && $user['agent_level'] < 1){
                    User::update(['agent_level' => 1],['uid' => $user['uid']]);
                }
            }
        }
        return true;
    }

}