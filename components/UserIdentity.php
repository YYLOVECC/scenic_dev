<?php

/**
 * 用户信息
 */
namespace app\components;

use app\services\func\RoleService;
use app\services\role\CRoleService;
use app\util\AESUtils;
use app\util\ArrayUtil;
use app\util\RedisUtil;
use Yii;
use Exception;
use app\util\YiiCookie;
use app\util\ConstantConfig;
use app\services\func\UsersService;

class UserIdentity
{
    private static $_user_info = null; //用户信息
    private static $_user_role_ids = null; // 用户角色信息
    private static $_user_feature_privilege = null; //用户功能权限
    private static $_user_data_privilege = null; //用户数据权限
    private static $_user_black_view_field_privilege = [];//用户字段查看权限黑名单
    private static $_user_black_edit_field_privilege = [];//用户字段编辑权限黑名单

    /**
     * @return array
     */
    public static function getUserBlackEditFieldPrivilege()
    {
        return self::$_user_black_edit_field_privilege;
    }

    /**
     * @param array $user_black_edit_field_privilege
     */
    public static function setUserBlackEditFieldPrivilege($user_black_edit_field_privilege)
    {
        self::$_user_black_edit_field_privilege = $user_black_edit_field_privilege;
    }

    /**
     * @return array
     */
    public static function getUserBlackViewFieldPrivilege()
    {
        return self::$_user_black_view_field_privilege;
    }

    /**
     * @param array $user_black_view_field_privilege
     */
    public static function setUserBlackViewFieldPrivilege($user_black_view_field_privilege)
    {
        self::$_user_black_view_field_privilege = $user_black_view_field_privilege;
    }

    /**
     * @return null
     * @throws Exception
     */
    public static function getUserInfo()
    {
        return UserIdentity::$_user_info;
    }

    /**
     * @param $user_info
     * @throws Exception
     */
    public static function setUserInfo($user_info)
    {
        UserIdentity::$_user_info = $user_info;
    }

    public static function getUserRoleIds()
    {
        return UserIdentity::$_user_role_ids;
    }

    public static function setUserRoleInfo($user_role_ids)
    {
        UserIdentity::$_user_role_ids = $user_role_ids;
    }

    /**
     * @return null
     */
    public static function getUserFeaturePrivilege()
    {
        return UserIdentity::$_user_feature_privilege;
    }

    /**
     * @param $user_feature_privilege
     */
    public static function setUserFeaturePrivilege($user_feature_privilege)
    {
        UserIdentity::$_user_feature_privilege = $user_feature_privilege;
    }

    /**
     * @return null
     */
    public static function getUserDataPrivilege()
    {
        return UserIdentity::$_user_data_privilege;
    }

    /**
     * @param $user_data_privilege
     */
    public static function setUserDataPrivilege($user_data_privilege)
    {
        UserIdentity::$_user_data_privilege = $user_data_privilege;
    }

    /**
     * 检测用户是否登陆
     * @return bool
     * @throws Exception
     */
    public static function isLogin()
    {
        $admin_token_str = YiiCookie::get(ConstantConfig::ADMIN_COOKIE_NAME);
        if (empty($admin_token_str)) {
            return false;
        }
        $admin_token = $admin_token_str['user_id'];
        //获取用户信息
        $admin_user_service = new UsersService();
        $user_info = $admin_user_service->findByPk($admin_token);
        if (empty($user_info) || $user_info['is_enable'] == ConstantConfig::ENABLE_FALSE ) {
            return false;
        }
        $role_ids = $admin_user_service->getRoleIdsById($admin_token);
        UserIdentity::setUserInfo($user_info);
        UserIdentity::setUserRoleInfo($role_ids);
        return true;
    }
    /**
     * 获取用户权限菜单
     */
    public static function getUserLeftMenus()
    {
        $user_info = UserIdentity::$_user_info;
        if(empty($user_info)){
            return [];
        }
        $user_id = $user_info['id'];
        //缓存获取用户菜单权限
        $data = json_decode(RedisUtil::hmget(Yii::$app->params['privilege_name'],  'user_left_menu_' . $user_id), true);
        if(!empty($data)){
            return $data;
        }
        //获取用户功能权限xxxxx
        $role_service = new RoleService();
        $user_feature_privileges = $role_service->getRoleFeaturePrivilegesByUserId($user_id);
        if(empty($user_feature_privileges)){
            return [];
        }
        self::setUserFeaturePrivilege($user_feature_privileges);

        //定义遍历后的数组
        $data = [];
        foreach ($user_feature_privileges as $k => $v) {
            if ($v['parent_id'] == 0) {
                $v['children'] = [];
                unset($v['actions']);
                array_push($data, $v);
                unset($user_feature_privileges[$k]);
            }
        }
        $data = array_reverse($data);
        foreach ($data as &$d) {
            foreach ($user_feature_privileges as $k => $v) {
                if ((int)$v['parent_id'] == (int)$d['id']) {
                    unset($v['actions']);
                    array_push($d['children'], $v);
                    unset($user_feature_privileges[$k]);
                }
            }
            $d['children'] = array_reverse($d['children']);
        }
        //用户左侧菜单缓存
        $expire_time = 2*24*60*60;//缓存2天
        RedisUtil::hmset(Yii::$app->params['privilege_name'], 'user_left_menu_' . $user_id,  json_encode($data), null,
            $expire_time);
        return $data;
    }
}