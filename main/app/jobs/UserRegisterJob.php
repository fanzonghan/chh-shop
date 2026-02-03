<?php


namespace app\jobs;

use app\services\user\UserServices;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;

/**
 * Class UserRegisterJob
 * @package app\jobs
 * @author xiaofan
 * @date 2026/1/31
 * 神兽保佑 永无bug
 */
class UserRegisterJob extends BaseJobs
{
    use QueueTrait;

    /**
     * 用户注册后置事件
     * @param $uid
     * @return bool
     */
    public function doJob($uid)
    {
        //节点下滑
        /** @var UserServices $userServices */
        $userServices = app()->make(UserServices::class);
        $userServices->nodeDown($uid);
        //找出全部上级
        $userServices->nodeUp($uid);
        return true;
    }
}