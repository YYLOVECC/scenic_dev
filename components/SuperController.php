<?php
/**
 * 订单系统父类
 */

namespace app\components;

use app\util\BitUtil;
use Exception;
use Yii;
use yii\web\Controller;
use yii\web\HttpException;

use app\util\ConstantConfig;
use app\util\YiiCookie;
use app\util\LogsFactory;
use app\services\func\UsersService;
use app\services\func\RoleService;
use app\services\super\CLogService;
use app\util\ResourceLogs;


class SuperController extends Controller
{
    public $user_info = [];
    public $scripts = [];
    public $css = [];
    public $user_feature_privileges = [];
    public $user_left_menus = [];

    public $module_id = 0;
    protected $_action_id = 0;
    protected $_resource_type = 0;
    protected $_action_key = 'view';
    protected $_action_name = '查看';

    public $user_role_ids = []; // 用户角色信息

    public function init()
    {
        //登陆检测
        $this->_isLogin();
        //获取用户权限
//        $this->_auth_popedom();
        //获取用户左侧菜单
        $this->_userLeftMenu();
    }

    /**
     * 根据url获取模块信息
     * @param $url
     * @return mixed
     * @throws Exception
     */
    public function getModuleIdByUrl($url)
    {
        $role_service = new RoleService();
        $module = $role_service->getMidByUrl($url);
        if (empty($module) || !$module['is_display']) {
            return null;
        }
        return intval($module['id']);
    }

    /**
     * 根据模块id获取用户模块权限信息
     * @param $module_id
     * @return mixed
     */
    public function getModuleById($module_id)
    {
        return $this->user_feature_privileges[$module_id];
    }


    /**
     * 检测模块访问权限
     * @param $module_id
     * @return bool
     */
    public function checkModuleAccess($module_id)
    {
        if (!$module_id) {
            return false;
        }
        if (!array_key_exists((string)$module_id, $this->user_feature_privileges)) {
            return false;
        }
        return true;
    }
    /**
     * 检测模块操作权限
     * @param $module_id
     * @param $action_key ：action操作的e_name
     * @return bool
     */
    public function checkModuleActionAccess($module_id, $action_key)
    {
        if (!$module_id) {
            return false;
        }
        if (!array_key_exists($module_id, $this->user_feature_privileges)) {
            return false;
        }

        $actions = $this->user_feature_privileges[$module_id]['actions'];
        $is_exist = false;
        foreach (array_values($actions) as $value) {
            if($value["e_name"] == $action_key){
                //行为存在，获取行为的ID e_name name
                $this->_action_id = (int)$value["id"];
                $this->_action_name = $value["name"];
                $this->_action_key = $action_key;
                $is_exist = true;
                break;
            }
        }
        if(!$is_exist){
            return false;
        }
        return true;
    }

    /**
     * 获取模块的行为
     * @param $module_id
     * @return array
     * @throws \yii\web\HttpException
     */
    public function getActionKeysByMid($module_id)
    {
        if (!array_key_exists($module_id, $this->user_feature_privileges)) {
            throw new HttpException(400);
        }
        $action_keys = [];
        $actions = $this->user_feature_privileges[$module_id]['actions'];
        foreach (array_values($actions) as $value) {
            array_push($action_keys, $value['e_name']);
        }
        return $action_keys;
    }
    /**
     * 检查是否登录
     */
    private function _isLogin()
    {
        //检测cookie
        $admin_token_str = YiiCookie::get(ConstantConfig::ADMIN_COOKIE_NAME);
        if (empty($admin_token_str)) {
            return $this->redirect('/site/login');
        }
        $admin_user = $admin_token_str['user_id'];
        //获取用户信息
        $admin_user_service = new UsersService();
        $user_info = $admin_user_service->findByPk($admin_user);
        if (empty($user_info) || $user_info['is_enable'] == ConstantConfig::ENABLE_FALSE ) {
            return $this->redirect('/site/login');
        }
        $this->user_info = $user_info;
        UserIdentity::setUserInfo($user_info);
        $role_ids = $admin_user_service->getRoleIdsById($admin_user);
        $this->user_role_ids = $role_ids;
        UserIdentity::setUserRoleInfo($role_ids);
        return true;
    }
    /**
     * 获取用户左侧目录树
     */
    private function _userLeftMenu()
    {
        //获取用户角色
        $user_id = $this->user_info['id'];
        //获取用户功能权限
        $role_service = new RoleService();
        $user_feature_privileges = $role_service->getRoleFeaturePrivilegesByUserId($user_id);
        if (empty($user_feature_privileges)) {
            return [];
        }
        $this->user_feature_privileges = $user_feature_privileges;
        UserIdentity::setUserFeaturePrivilege($user_feature_privileges);

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
                if (intval($v['parent_id']) == intval($d['id'])) {
                    unset($v['actions']);
                    array_push($d['children'], $v);
                    unset($user_feature_privileges[$k]);
                }
            }
            $d['children'] = array_reverse($d['children']);
        }
        $this->user_left_menus = $data;
    }

    /**
     * @param $resource_ids
     * @return bool
     * @throws HttpException
     */

    public function saveResourceLogs($resource_ids)
    {
        if(empty($this->_resource_type) or empty($resource_ids) or
            !in_array($this->_resource_type, ConstantConfig::allResourceType()) or
            !in_array($this->_action_key, $this->getActionKeysByMid($this->module_id))){
            return false;
        }
        //将日志写入
        $service = new CLogService();
        $service->saveResourceActionLogs($resource_ids, $this->_resource_type, $this->_action_id, $this->_action_name);
        return true;
    }
}