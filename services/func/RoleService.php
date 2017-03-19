<?php
/**
 * Created by PhpStorm.
 * User: teresas
 * Date: 8/11/15
 * Time: 5:38 PM
 */
namespace app\services\func;

use app\models\RoleFieldPrivilegesModel;
use app\models\RolesModel;
use Yii;

use app\util\RedisUtil;
use app\util\ConstantConfig;
use app\models\ModulesModel;
use app\models\RoleDataPrivilegesModel;
use app\models\RoleModuleActionsModel;
use app\models\RoleModulesModel;
use app\services\store\CStoreService;

class RoleService
{

    /**
     * 根据用户id获取用户角色的功能权限信息
     * @param $user_id
     * @return array
     */
    public function getRoleFeaturePrivilegesByUserId($user_id)
    {
        if(empty($user_id)){
            return [];
        }
        //缓存获取功能权限
        $user_feature_privileges = json_decode(RedisUtil::hmget(Yii::$app->params['privilege_name'],
            'feature_privilege_'.$user_id), true);
        if(!empty($user_feature_privileges)){
            return $user_feature_privileges;
        }
        //查询用户角色
        $user_service = new UsersService();
        $role_ids = $user_service->getRoleIdsById($user_id);
        if(empty($role_ids)){
            return [];
        }
        //获取角色模块id
        $role_module_model = new RoleModulesModel();
        $result = $role_module_model->getByRoleIds($role_ids);
        $module_ids = [];
        foreach($result as $value){
            array_push($module_ids, $value['module_id']);
        }
        //获取模块信息
        $feature_service = new FeaturesAuthService();
        $role_modules = [];
        if(!empty($module_ids)){
            $module_ids = array_unique($module_ids);
            $valid_modules = $feature_service->getEnableModules();
            foreach($valid_modules as $key=>$value)
            {
                if(in_array($value['id'], $module_ids)){
                    $role_modules[$value['id']] = $value;
                }
            }
        }
        //获取角色模块行为
        $role_module_action_model = new RoleModuleActionsModel();
        $module_action_result = $role_module_action_model->getByRoleIds($role_ids);
        $module_actions = [];
        if(!empty($module_action_result)){
            foreach($module_action_result as $value){
                if(!array_key_exists($value['module_id'], $module_actions)){
                    $module_actions[$value['module_id']] = [];
                }
                array_push($module_actions[$value['module_id']], $value['action_id']);
            }
        }
        $actions = $feature_service->getEnableActions();
        $valid_actions = [];
        foreach($actions as $action){
            $valid_actions[$action['id']]=$action;
        }
        foreach($role_modules as $key=>&$value) {
            $value['actions'] = [];
            if (array_key_exists($key, $module_actions)) {
                $action_ids = $module_actions[$key];
                foreach ($action_ids as $a_id) {
                    $value['actions'][$a_id] = $valid_actions[$a_id];
                }
            }
        }
        //功能权限缓存
        $expire_time = 2*24*60*60;//缓存2天
        RedisUtil::hmset(Yii::$app->params['privilege_name'], 'feature_privilege_'.$user_id, json_encode($role_modules),
            null, $expire_time);
        return $role_modules;
    }
    /**
     * 根据模块路径获取模块信息
     * @param $url
     * @return array|bool
     */
    public function getMidByUrl($url)
    {
        //获取模块信息
        $module_model = new ModulesModel();
        $module_model->setPageUrl($url);
        return $module_model->findByUrl();
    }
}