<?php
/**
 * User: bcpmai
 * Date: 15-8-3
 * Time: 下午4:21
 */

namespace app\services\func;

use app\models\ActionsModel;
use app\models\RoleModuleActionsModel;
use app\models\UserRolesModel;
use app\services\role\CRoleService;
use app\util\RedisUtil;
use Exception;

use Yii;
use yii\web\Request;

use app\forms\AdminUsersForm;
use app\models\AdminUsersModel;
use app\models\DepartmentsModel;
use app\util\ConstantConfig;

class UsersService
{
    /**
     * 用户列表分页查询
     * @param $request
     * @return array
     */
    public function listData(Request $request)
    {
        $admin_users_model = new AdminUsersModel();
        $admin_users_model->setId($request->post('id', null));
        $admin_users_model->setName($request->post('name', null));
        $admin_users_model->setEmail($request->post('email', null));
        $admin_users_model->setStatus($request->post('status', -1));
        $admin_users_model->setRoleId($request->post('role_id', -1));
        $page = $request->post('start', 0);
        $page_size = $request->post('page_size', 0);

        $num = $admin_users_model->countByPk();
        $users = $admin_users_model->findList($page, $page_size);

        // 所有有效角色
        $role_service = new CRoleService();
        $roles = $role_service->getValidRoles();

        //// 转换角色信息
        $roles_mapping = [];
        foreach ($roles as $value) {
            $roles_mapping[$value['id']] = $value['name'];
        }

        // 查询用户角色中间表
        $user_role_model = new UserRolesModel();
        $user_role_infos = $user_role_model->getAllUserRole();

        //// 转换中间表数据
        $user_role_mapping = [];
        foreach ($user_role_infos as $value) {
            if (!array_key_exists($value['user_id'], $user_role_mapping)) {
                $user_role_mapping[$value['user_id']] = [];
            }
            array_push($user_role_mapping[$value['user_id']], $value['role_id']);
        }

        // 拼装用户角色信息
        foreach($users as $key => $user) {
            $current_user_role_names = [];
            if(array_key_exists($user['id'], $user_role_mapping)){
                $current_user_roles = $user_role_mapping[$user['id']];
                foreach($current_user_roles as $user_role_id) {
                    array_push($current_user_role_names, $roles_mapping[$user_role_id]);
                }
            }
            $users[$key]['role_names'] = join(",", $current_user_role_names);
            $users[$key]['created_date'] = (int)$user['created_at'] ? date('Y/m/d H:i:s', (int)$user['created_at']) : '';
            $users[$key]['last_login_date'] = (int)$user['last_login_at'] ? date('Y/m/d H:i:s', (int)$user['last_login_at']) : '';
        }

        return ['success' => true, 'count' => $num, 'data' => $users];
    }

    /**
     * 根据ID 查询单条记录
     * @param $id
     * @return array|bool
     */
    public function findByPk($id)
    {
        $admin_users_model = new AdminUsersModel();
        $admin_users_model->setId($id);
        $user = $admin_users_model->findByPk();
        return $user;
    }

    /**
     * 根据ID修改禁用状态
     * @param $id
     * @param $status
     * @return bool
     */
    public function updateDisableByPk($id, $status)
    {
        $connection = Yii::$app->db;
        $connection->open();

        $admin_users_model = new AdminUsersModel();
        $transaction = $connection->beginTransaction();

        try {
            $admin_users_model->setId($id);
            $admin_users_model->setIsEnable($status);
            $admin_users_model->updateDisableByPk();
            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollBack();
            $connection->close();
            return false;
        }

        $connection->close();

        return true;
    }

    /**
     * 添加用户
     * @param AdminUsersForm $form
     * @return bool
     */
    public function addAdminUser(AdminUsersForm $form)
    {
        $admin_user_model = new AdminUsersModel();
        $admin_user_model->setName($form->name);
        $admin_user_model->setEmail($form->email);
        $admin_user_model->setRoleId($form->roles_id);
        $admin_user_model->setDescription($form->description);
        $admin_user_model->setIsEnable(ConstantConfig::ENABLE_TRUE);
        $admin_user_model->setCreatedAt(Yii::$app->params['current_time']);
        $admin_user_model->setUpdatedAt(Yii::$app->params['current_time']);
        $salt = mt_rand(100000,999999);
        $admin_user_model->setSalt($salt);
        $original_password = md5(md5('abc1234').$salt);
        $admin_user_model->setPassword($original_password);
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $id = $admin_user_model->create();
            //插入用户角色表
            $user_role_model = new UserRolesModel();
            $user_role_model->setUserId($id);
            $user_role_model->setRoleId($form->roles_id);
            $user_role_model->setCreatedAt((Yii::$app->params['current_time']));
            $user_role_model->setUpdatedAt(Yii::$app->params['current_time']);
            $user_role_model->create();
            $transaction->commit();
        }catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
        return true;
    }

    /**
     * 根据ID修改用户信息
     * @param AdminUsersForm $form
     * @return bool
     */
    public function updateByPk(AdminUsersForm $form)
    {
        //修改用户表
        $admin_users_model = new AdminUsersModel();
        $admin_users_model->setId($form->id);
        $admin_users_model->setName($form->name);
        $admin_users_model->setEmail($form->email);
        $admin_users_model->setDescription($form->description);
        $admin_users_model->setUpdatedAt(Yii::$app->params['current_time']);

        //修改用户关联角色表
        $user_role_model = new UserRolesModel();
        $user_role_model->setUserId($form->id);
        $user_role_model->setCreatedAt((Yii::$app->params['current_time']));
        $user_role_model->setUpdatedAt(Yii::$app->params['current_time']);

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {

            $admin_users_model->updateByPk();
            $user_role_model->deleteByUserId();
            if ($form->roles_id != 0) {
                $role_array = explode(',', $form->roles_id);
                $command = $user_role_model->createBatch();

                foreach ($role_array as $role_id) {
                    $user_role_model->setRoleId($role_id);
                    $user_role_model->createBatchExecute($command);
                }
            }

            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
        return true;
    }

    /**
     * 根据用户id获取用户角色
     * @param $user_id
     * @return array
     */
    public function getRoleIdsById($user_id)
    {
        //获取用户有效角色
        $user_role_model = new UserRolesModel();
        $user_role_model->setUserId($user_id);
        $user_roles = $user_role_model->getRoleByUserId();
        $role_ids = [];
        foreach($user_roles as $value)
        {
            array_push($role_ids, intval($value['role_id']));
        }
        return $role_ids;
    }
    /**
     * 根据行为获取相关用户
     * @param $action_key
     * @return array
     */
    public function getUsersByAction($action_key)
    {
        if(empty($action_key)){
            return [];
        }
        //查询客审操作信息
        $action_model = new ActionsModel();
        $action_model->setEName($action_key);
        $action = $action_model->findByEName();
        if(empty($action) || $action['is_enable'] == ConstantConfig::ENABLE_FALSE){
            return [];
        }
        //查询行为操作关联角色
        $role_module_action_model = new RoleModuleActionsModel();
        $role_module_action_model->setActionId($action['id']);
        $role_module_actions = $role_module_action_model->getByActionId();
        if(empty($role_module_actions)){
            return [];
        }
        $role_ids = [];
        foreach($role_module_actions as $value){
            array_push($role_ids, $value['role_id']);
        }
        //查询角色关联用户id
        $user_role_model = new UserRolesModel();
        $user_roles = $user_role_model->getByRoleIds($role_ids);
        $user_ids = [];
        foreach($user_roles as $value){
            array_push($user_ids, $value['user_id']);
        }
        //补充用户信息
        $user_model = new AdminUsersModel();
        $users = $user_model->findByPks($user_ids);
        return $users;
    }

    /**
     * 获取所有用户的name和email
     * @return array
     */
    public function getAllUserInfo(){
        $admin_user_model = new AdminUsersModel();
        $all_user_info = $admin_user_model->getAllUserInfo();
        if (!empty($all_user_info)) {
            return $all_user_info;
        }
    }

    /**
     * 获取客审用户
     * @return array|bool
     */
    public function getAuditUsers()
    {
        //查询客审操作信息
        $action_model = new ActionsModel();
        $action_model->setEName('audit');
        $action = $action_model->findByEName();
        if(empty($action) || $action['is_enable'] == ConstantConfig::ENABLE_FALSE){
            return [];
        }
        //查询客审操作关联角色
        $role_module_action_model = new RoleModuleActionsModel();
        $role_module_action_model->setActionId($action['id']);
        $role_module_actions = $role_module_action_model->getByActionId();
        if(empty($role_module_actions)){
            return [];
        }
        $role_ids = [];
        foreach($role_module_actions as $value){
            array_push($role_ids, $value['role_id']);
        }
        //查询角色关联用户id
        $user_role_model = new UserRolesModel();
        $user_roles = $user_role_model->getByRoleIds($role_ids);
        $user_ids = [];
        foreach($user_roles as $value){
            array_push($user_ids, $value['user_id']);
        }
        //补充用户信息
        $user_model = new AdminUsersModel();
        $users = $user_model->findByPks($user_ids);
        return $users;
    }
}