<?php
/**
 * Created by PhpStorm.
 * User: teresas
 * Date: 8/11/15
 * Time: 5:38 PM
 */
namespace app\services\func;

use app\models\RoleFieldPrivilegesModel;
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
        return $role_modules;
    }

    /**
     * 根据用户id获取用户角色的字段权限信息
     * @param $user_id
     * @return array
     */
//    public function getRoleFieldPrivilegesByUserId($user_id)
//    {
//        if(empty($user_id)){
//            return [];
//        }
//        //查询用户角色
//        $user_service = new UsersService();
//        $role_ids = $user_service->getRoleIdsById($user_id);
//        if(empty($role_ids)){
//            return [];
//        }
//
//        //获取角色的字段权限
//        $role_field_privilege_model = new RoleFieldPrivilegesModel();
//        $role_field_privileges = $role_field_privilege_model->getByRoleIds($role_ids);
//        //补充字段英文名
//        if(!empty($role_field_privileges)){
//            $field_ids = [];
//            $user_field_privileges = [];
//            foreach($role_field_privileges as $value){
//                $field_id = $value['field_privilege_id'];
//                array_push($field_ids, $field_id);
//                if(!array_key_exists($field_id, $user_field_privileges)){
//                    $user_field_privileges[$field_id] = $value;
//                }else{
//                    if((int)$user_field_privileges[$field_id]['operation_type']<(int)$value['operation_type']){
//                        $user_field_privileges[$field_id] = $value;
//                    }
//                }
//            }
//        }
//
//        return $user_field_privileges;
//    }


    /**
     * 根据用户id获取用户角色的数据权限信息
     * @param $user_id
     * @return array
     */
//    public function getRoleDataPopedomsByUserId($user_id)
//    {
//        if(empty($user_id)){
//            return [];
//        }
//        //查询用户角色
//        $user_service = new UsersService();
//        $role_ids = $user_service->getRoleIdsById($user_id);
//        if(empty($role_ids)){
//            return [];
//        }
//
//        //获取角色的数据权限
//        $role_data_popedom_model = new RoleDataPrivilegesModel();
//        $role_data_popedoms = $role_data_popedom_model->getByRoleIds($role_ids);
//        $result = [];
//        foreach($role_data_popedoms as $value){
//            if(!array_key_exists($value['data_privilege_type'], $result)){
//                $result[$value['data_privilege_type']] = [];
//            }
//            array_push($result[$value['data_privilege_type']], $value['data_id']);
//        }
//        foreach(array_keys($result) as $value){
//            if(intval($value) == ConstantConfig::DATA_STORE){
//                //店铺权限数据获取
//                $store_ids = $result[$value];
//                $store_service = new CStoreService();
//                $stores = $store_service->getEnableStores();
//                $result[$value] = [];
//                foreach($store_ids as $s_id){
//                    $result[$value][$s_id] = $stores[$s_id];
//                }
//            }
//        }
//    }

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