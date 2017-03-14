<?php
/**
 * 角色基础服务类
 */
namespace app\services\role;

use app\models\RoleFieldPrivilegesModel;
use app\util\BitUtil;
use Exception;

use Yii;

use app\util\ConstantConfig;
use app\models\AdminUsersModel;
use app\models\ComplexModel;
use app\models\RoleDataPrivilegesModel;
use app\models\RoleModuleActionsModel;
use app\models\RoleModulesModel;
use app\models\RolesModel;
use app\models\UserRolesModel;
use app\components\UserIdentity;
use app\util\RedisUtil;

class CRoleService
{

    /**
     * 条件检索角色信息
     * @param $params
     * @param $start
     * @param $page_size
     * @param $ordinal_str
     * @param $ordinal_type
     * @return array
     */
    public function searchRoleList($params, $start, $page_size, $ordinal_str, $ordinal_type)
    {
        $roles_model = new RolesModel();
        //获取角色列表
        $count = $roles_model->countSearchRoles($params);
        $roles = $roles_model->searchRoles($params, $start, $page_size, $ordinal_str, $ordinal_type);
        foreach ($roles as $key=>$role) {
            $roles[$key]['created_date'] = (int)$role['created_at'] ? date('Y/m/d H:i:s', (int)$role['created_at']) : '';
        }
        return ['success'=>true, 'count'=>$count, 'data'=>$roles];
    }


    /**
     * 条件检索角色用户信息
     * @param $role_id
     * @param $params
     * @param $start
     * @param $page_size
     * @param $ordinal_str
     * @param $ordinal_type
     * @return array
     */
    public function searchRoleUserList($role_id, $params, $start, $page_size, $ordinal_str, $ordinal_type)
    {
        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();

        $model = new UserRolesModel();
        $count = 0;
        $users = [];
        if($params['user_id']){//指定用户ID
            $model->setRoleId($role_id);
            $model->setUserId($params['user_id']);
            $model->setStatus(ConstantConfig::STATUS_DEFAULT);
            $user_role = $model->getByUserId();
            if(!empty($user_role)){
                //补充用户信息
                $user_model = new AdminUsersModel();
                $user_model->setId($params['user_id']);
                $user_info = $user_model->findByPk();
                if(!empty($user_info)){
                    $count = 1;
                    $user_role['user_name'] = $user_info['name'];
                    $user_role['is_enable'] = $user_info['is_enable'];
                    $user_role['user_created_at'] = $user_info['created_at'];
                    array_push($users, $user_role);
                }
            }
        }else{
            //检索用户列表
            unset($params['user_id']);
            $complex_model = new ComplexModel();
            $count = $complex_model->countSearchRoleUsers($role_id, $params);
            $users = $complex_model->searchRoleUsers($role_id, $params, $start, $page_size, $ordinal_str, $ordinal_type);
        }
        //时间戳转换
        if(!empty($users)){
            foreach($users as &$value){
                $value['created_date'] = intval($value['created_at'])?date('Y/m/d H:i:s', intval($value['created_at'])):'';
                $value['user_created_date'] = intval($value['user_created_at'])?date('Y/m/d H:i:s', intval($value['user_created_at'])):'';
            }
        }

        $connection->close();
        return ['success'=>True, 'count'=>$count, 'data'=>$users];
    }


    /**
     * 获取有效角色
     * @return int
     */
    public function getValidRoles(){
        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();

        $model = new RolesModel();
        $model->setIsEnable(ConstantConfig::ENABLE_TRUE);
        $result = $model->getEnableRoles();

        //关闭数据库
        $connection->close();
        return $result;
    }

    /**
     * 根据id获取角色信息
     * @param $role_id
     * @return string
     */
    public function getById($role_id){
        if(empty($role_id)){
            return '';
        }
        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();
        $model = new RolesModel();
        $model->setId($role_id);
        $role = $model->getById();
        $connection->close();
        return $role;
    }

    /**
     * 批量检索角色信息
     * @param $ids
     * @return array
     */
    public function getByIds($ids){
        if(empty($ids)){
            return [];
        }
        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();
        $model = new RolesModel();
        $roles = $model->getByIds($ids);
        $connection->close();
        return $roles;
    }

    /**
     * 新增角色
     * @param $form_data
     * @return bool
     */
    public function create($form_data)
    {
        if(empty($form_data)){
            return false;
        }
        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();
        try{
            $model = new RolesModel();
            $model->setName($form_data['role_name']);
            $model->setDescription($form_data['description']);
            $model->setParentId($form_data['parent_id']);
            $model->setLevel($form_data['level']);
            $model->setIsEnable($form_data['is_enable']);
            $model->setCreateUserId(UserIdentity::getUserInfo()['id']);
            $model->setCreateUserName(UserIdentity::getUserInfo()['name']);
            $model->setCreatedAt(Yii::$app->params['current_time']);
            $model->setUpdatedAt(Yii::$app->params['current_time']);
            $model->create();
            $result = true;
        } catch (Exception $e) {
            $result = false;
        }
        //关闭数据库
        $connection->close();
        return $result;
    }

    /**
     * 编辑角色
     * @param $form_data
     * @param $role
     * @return bool
     */
    public function update($form_data, $role)
    {
        if(empty($form_data)){
            return false;
        }

        //获得数据库连接并开启DB
        $connection = Yii::$app->db;

        $transaction = $connection->beginTransaction();

        try{
            $role_id = $form_data['role_id'];
            $is_enable = intval($form_data['is_enable']);
            $current_time = Yii::$app->params['current_time'];
            $model = new RolesModel();
            //修改角色基本信息
            $model->setId($role_id);
            $model->setName($form_data['role_name']);
            $model->setDescription($form_data['description']);
            $model->setParentId($form_data['parent_id']);
            $model->setLevel($form_data['level']);
            $model->setUpdateUserId(UserIdentity::getUserInfo()['id']);
            $model->setUpdateUserName(UserIdentity::getUserInfo()['name']);
            $model->setUpdatedAt($current_time);
            if(!$model->update()){
                throw new Exception;
            }

            //获取子角色id
            $child_ids = $this->getChildRoleIds($role_id);
            //角色层级改变，子角色层级应改变
            if($child_ids && intval($form_data['level']) != intval($role['level'])){
                $inc_level = intval($form_data['level']) - intval($role['level']);
                if(!$model->updateLevelByIds($child_ids, $inc_level)){
                    throw new Exception;
                }
            }

            //检测角色停启用状态变更
            if($is_enable != intval($role['is_enable'])){
                if($is_enable == ConstantConfig::ENABLE_FALSE){
                    //停用角色
                    $result = $this->disable($role_id, $connection, $transaction, $current_time);
                    if(!$result['success']){
                        throw new Exception;
                    }
                }elseif($is_enable == ConstantConfig::ENABLE_TRUE){
                    //启用角色
                    $result = $this->enable($role_id, $connection, $current_time);
                    if(!$result['success']){
                        throw new Exception;
                    }
                }
            }

            $transaction->commit();
            $result = true;
        } catch (Exception $e) {
            $transaction->rollBack();
            $result = false;
        }
        $connection->close();
        return $result;
    }

    /**
     * 停用角色
     * @param $role_id
     * @param null $connection
     * @param null $transaction
     * @param $operate_time
     * @return array|bool
     */
    public function disable($role_id, $connection=null, $transaction=null, $operate_time=0)
    {
        if(empty($role_id)){
            return ['success'=> false, 'msg'=>'参数传递错误：role_id'];
        }
        $has_conn = false;
        $has_tran = false;
        if(!$connection){
            $has_conn = true;
            $connection = Yii::$app->db;
            $connection->open();
        }
        if(!$transaction){
            $has_tran = true;
            $transaction = $connection->beginTransaction();
        }
        try{
            $role_model = new RolesModel();
            //查询当前角色信息及状态
            $role_model->setId($role_id);
            $role_info = $role_model->getById();
            if(empty($role_info)){
                if($has_conn){
                    $connection->close();
                }
                return ['success'=>false, 'msg'=>'角色不存在或已被删除'];
            }
            if($role_info['is_enable'] == ConstantConfig::ENABLE_FALSE){
                if($has_conn){
                    $connection->close();
                }
                return ['success'=>true, 'msg'=>'角色已停用', 'disable_ids'=> [$role_id]];
            }
            $operate_time = $operate_time?$operate_time:Yii::$app->params['current_time'];

            //停用当前角色及其子角色
            $disable_ids = $this->getChildRoleIds($role_id);
            array_push($disable_ids, $role_id);
            $role_model->setIsEnable(ConstantConfig::ENABLE_FALSE);
            $role_model->setUpdateUserId(UserIdentity::getUserInfo()['id']);
            $role_model->setUpdateUserName(UserIdentity::getUserInfo()['name']);
            $role_model->setUpdatedAt($operate_time);
            if(!$role_model->updateStateByIds($disable_ids)) {
                throw new Exception;
            }
            //停用角色用户关系
            $user_role_model = new UserRolesModel();
            $user_role_model->setStatus(ConstantConfig::STATUS_DELETE);
            $user_role_model->setUpdatedAt($operate_time);
            if(!$user_role_model->updateStatusByRids($disable_ids)) {
                throw new Exception;
            }

            if($has_tran){
                $transaction->commit();
            }
            $result = ['success'=>true, 'msg'=>'停用操作成功', 'disable_ids'=>$disable_ids];
        }catch (Exception $e){
            if($has_tran){
                $transaction->rollBack();
            }
            $result = ['success'=>false, 'msg'=>'停用操作失败'];
        }
        //关闭数据库连接
        if($has_conn){
            $connection->close();
        }
        return $result;
    }

    /**
     * 启用角色
     * @param $role_id
     * @param null $connection
     * @param $operate_time
     * @return array|bool
     */
    public function enable($role_id, $connection=null, $operate_time=0)
    {
        if(empty($role_id)){
            return ['success'=> false, 'msg'=>'参数传递错误：role_id'];
        }
        //获得数据库连接并开启DB
        $has_conn = false;
        $has_tran = false;
        if(!$connection){
            $has_conn = true;
            $connection = Yii::$app->db;
            $connection->open();
        }
        $transaction = $connection->getTransaction();
        if(!$transaction){
            $has_tran = true;
            $transaction = $connection->beginTransaction();

        }
        try{
            $role_model = new RolesModel();
            //查询当前角色信息及状态
            $role_model->setId($role_id);
            $role_info = $role_model->getById();
            if(empty($role_info)){
                if($has_conn){
                    $connection->close();
                }
                return ['success'=>false, 'msg'=>'角色不存在或已被删除'];
            }
            if($role_info['is_enable'] == ConstantConfig::ENABLE_TRUE){
                if($has_conn){
                    $connection->close();
                }
                return ['success'=>true, 'msg'=>'角色已启用', 'disable_ids'=> [$role_id]];
            }
            $operate_time = $operate_time?$operate_time:Yii::$app->params['current_time'];

            //启用当前角色及其上级角色
            $enable_ids = $this->getParentRoleIds($role_id);
            array_push($enable_ids, $role_id);
            $role_model->setIsEnable(ConstantConfig::ENABLE_TRUE);
            $role_model->setUpdateUserId(UserIdentity::getUserInfo()['id']);
            $role_model->setUpdateUserName(UserIdentity::getUserInfo()['name']);
            $role_model->setUpdatedAt($operate_time);
            if(!$role_model->updateStateByIds($enable_ids)) {
                throw new Exception;
            }

            //启用角色用户关系
            $user_role_model = new UserRolesModel();
            $user_role_model->setStatus(ConstantConfig::STATUS_DEFAULT);
            $user_role_model->setUpdatedAt($operate_time);
            if(!$user_role_model->updateStatusByRids($enable_ids)) {
                throw new Exception;
            }
            if($has_tran){
                $transaction->commit();
            }
            $result = ['success'=>true, 'msg'=>'停用操作成功', 'enable_ids'=>$enable_ids];
        }catch (Exception $e){
            if($has_tran){
                $transaction->rollBack();
            }
            $result = ['success'=>false, 'msg'=>'停用操作失败'];
        }
        //关闭数据库连接
        if($has_conn){
            $connection->close();
        }
        return $result;
    }

    /**
     * 删除角色
     * @param $role_id
     * @param int $operate_time
     * @return array
     */
    public function delete($role_id,$operate_time=0)
    {
        if (empty($role_id)) {
            return ['success' => false, 'msg' => '参数传递错误：role_id'];
        }

        //获得数据库连接并开启事务
        $has_tran = false;
        $connection = Yii::$app->db;
        $transaction = $connection->getTransaction();
        if(!$transaction){
            $has_tran = true;
            $transaction = $connection->beginTransaction();
        }
        try{
            //查询角色信息
            $model = new RolesModel();
            $model->setId($role_id);
            $role = $model->getById();
            if(!$role){
                return ['success' => true, 'msg' => '角色信息不存在或已被删除', 'delete_ids' =>[$role_id]];
            }
            if($role['is_enable'] == ConstantConfig::ENABLE_TRUE) {
                return ['success' => false, 'msg' => '请先停用该角色再进行删除操作'];
            }

            $operate_time = $operate_time?$operate_time:Yii::$app->params['current_time'];

            //删除角色及其子角色
            $model->setStatus(ConstantConfig::STATUS_DELETE);
            $model->setUpdateUserId(UserIdentity::getUserInfo()['id']);
            $model->setUpdateUserName(UserIdentity::getUserInfo()['name']);
            $model->setUpdatedAt($operate_time);
            $child_ids = $this->getChildRoleIds($role_id);
            array_push($child_ids, $role_id);
            $model->setStatus(ConstantConfig::STATUS_DELETE);
            if(!$model->updateStatusByIds($child_ids)) {
                throw new Exception;
            }
            if($has_tran){
                $transaction->commit();
            }
            $result = ['success' => True, 'msg' => '删除操作成功'];
        }catch (Exception $e){
            if($has_tran){
                $transaction->rollBack();
            }
            $result = ['success' => False, 'msg' => '删除操作失败'];
        }
        return $result;
    }


    /**
     * 根据父亲节点id获取所有子节点id
     * @param $parent_id
     * @return array
     */
    public function getChildRoleIds($parent_id)
    {
        $tree = [];
        if(!$parent_id){
            return [];
        }
        if($parent_id == -1){
            $parent_id = 0;
        }
        #获取parent_id的子节点
        $model = new RolesModel();
        $model->setParentId($parent_id);
        $model->setIsEnable(ConstantConfig::ENABLE_TRUE);
        $data = $model->getRoleByPid();
        if(!$data){
            return $tree;
        }
        foreach($data as $k => $v) {
            $d_id = intval($v['id']);
            array_push($tree, $d_id);
            //递归获取子节点
            $tree = array_merge($tree, $this->getChildRoleIds($d_id));
        }
        return $tree;
    }

    /**
     * 获取指定角色id的上级角色ids
     * @param int $role_id
     * @return array|string
     */
    public function getParentRoleIds($role_id)
    {
        $parent_ids = [];
        if(empty($role_id)){
            return [];
        }
        #获取当前角色信息
        $model = new RolesModel();
        $model->setId($role_id);
        $model->setIsEnable(ConstantConfig::ENABLE_TRUE);
        $child_role = $model->getById();
        if(empty($child_role)){
            return [];
        }
        if(!intval($child_role['parent_id'])) {
            return [];
        }
        $parent_id = $child_role['parent_id'];
        array_push($parent_ids, $parent_id);
        //获取父亲节点信息
        $model->setId($parent_id);
        $parent_role = $model->getById();
        if(intval($parent_role['parent_id'])) {
            //递归获取父亲节点
            $parent_ids = array_merge($parent_ids, $this->getParentRoleIds($parent_id));
        }
        return $parent_ids;
    }
    /**
     * 获取角色模块
     * @param $role_id
     * @return array
     */
    public function getRoleModules($role_id){
        if(empty($role_id)){
            return [];
        }

        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();

        $role_module_model = new RoleModulesModel();
        $role_module_model->setRoleId($role_id);
        $result = $role_module_model->getByRoleId();
        //关闭数据库
        $connection->close();
        return $result;
    }

    /**
     * 获取角色模块行为
     * @param $role_id
     * @return array
     */
    public function getRoleModuleActions($role_id){
        if(empty($role_id)){
            return [];
        }

        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();

        $role_module_action_model = new RoleModuleActionsModel();
        $role_module_action_model->setRoleId($role_id);
        $result = $role_module_action_model->getByRoleId();
        //关闭数据库
        $connection->close();
        return $result;
    }


    /**
     * 编辑角色功能权限
     * @param $role_id
     * @param $feature_ids
     * @return array
     */
    public function updateRoleFeaturePrivilege($role_id, $feature_ids)
    {
        if(empty($role_id)){
            return ['success'=>false, 'msg'=>'参数传递错误:role_id'];
        }
        //获取功能模块id 模块行为id
        $module_ids = [];
        $module_action_ids = [];
        foreach($feature_ids as $value){
            if(stripos($value, '_')){
                array_push($module_ids, explode('_', $value)[0]);
                array_push($module_action_ids, $value);
            }else{
                array_push($module_ids, intval($value));
            }
        }
        $module_ids = array_unique($module_ids);
        //获得数据库连接并开启DB
        $connection = Yii::$app->db;
        $connection->open();
        $transaction = $connection->beginTransaction();
        try{
            //检测角色是否存在
            $role_model = new RolesModel();
            $role_model->setId($role_id);
            $role_info = $role_model->getById();
            if(empty($role_info)){
                return ['success'=>false, 'msg'=>'角色信息不存在'];
            }

            $current_time = Yii::$app->params['current_time'];
            //模块权限处理
            $module_result = $this->_updateRoleModules($role_id, $module_ids, $current_time);

            if(!$module_result['success']){
                throw new Exception;
            }
            //模块角色处理
            $module_action_result = $this->_updateRoleModuleActions($role_id, $module_action_ids, $current_time);
            if(!$module_action_result['success']){
                throw new Exception;
            }
            $transaction->commit();
            $result = ['success'=>true, 'msg'=>'功能权限更新成功'];

        }catch (Exception $e){
            $transaction->rollBack();
            $result = ['success'=>false, 'msg'=>'功能权限更新失败:'.$e];
        }
        $connection->close();
        return $result;
    }


    /**
     * 角色功能模块变更
     * @param $role_id
     * @param $module_ids
     * @param int $operate_time
     * @return array
     */
    private function _updateRoleModules($role_id, $module_ids, $operate_time=0)
    {
        //获取角色模块，相同则返回，不同则修改数据权限
        $role_module_model = new RoleModulesModel();
        $role_module_model->setRoleId($role_id);
        $role_ordinal_modules = $role_module_model->getAllByRoleId();
        $ordinal_module_ids = [];
        foreach ($role_ordinal_modules as $value) {
            array_push($ordinal_module_ids, intval($value['module_id']));
        }

        //获取减少的模块权限
        $reduce_module_ids = array_diff($ordinal_module_ids, $module_ids);

        //获取新增的模块权限
        $add_module_ids = array_diff($module_ids, $ordinal_module_ids);

        //相同的模块
        $common_module_ids = array_intersect($module_ids, $ordinal_module_ids);

        $has_change = false;

        $role_module_model = new RoleModulesModel();
        $role_module_model->setRoleId($role_id);
        if(!empty($common_module_ids)){//关联模块未变更
            //获取需启用关系的模块
            $enable_module_ids = [];
            foreach($role_ordinal_modules as $value){
                if(intval($value['status']) == ConstantConfig::STATUS_DELETE and in_array($value['module_id'], $common_module_ids)){
                    array_push($enable_module_ids, $value['module_id']);
                }
            }

            if(!empty($enable_module_ids))                                                                             {
                $role_module_model->setStatus(ConstantConfig::STATUS_DEFAULT);
                $role_module_model->setUpdatedAt($operate_time);
                if(!$role_module_model->updateStatusByModuleIds($enable_module_ids)){
                    return ['success'=>false];
                }
                $has_change = true;
            }
        }
        //功能权限减少，停用关系
        if(!empty($reduce_module_ids)){
            $role_module_model->setStatus(ConstantConfig::STATUS_DELETE);
            $role_module_model->setUpdatedAt($operate_time);
            if(!$role_module_model->updateStatusByModuleIds($reduce_module_ids)){
                return ['success'=>false];
            }
            $has_change = true;
        }

        //新增功能权限
        if(!empty($add_module_ids)){
            $command = $role_module_model->createBatch();
            $status = ConstantConfig::STATUS_DEFAULT;
            foreach ($add_module_ids as $s_id) {
                $role_module_model->setRoleId($role_id);
                $role_module_model->setModuleId($s_id);
                $role_module_model->setStatus($status);
                $role_module_model->setCreatedAt($operate_time);

                $role_module_model->createBatchExecute($command);
            }
            $has_change = true;
        }
        return ['success'=>true, 'has_change'=>$has_change];
    }


    /**
     * 角色模块行为变更
     * @param $role_id
     * @param $module_action_ids
     * @param int $operate_time
     * @return array
     */
    private function _updateRoleModuleActions($role_id, $module_action_ids, $operate_time=0)
    {
        $module_action_relations = [];
        foreach($module_action_ids as $value){
            $action_array = explode('_', $value);
            $module_action_relations[$action_array[2]] = [$action_array[0], $action_array[1]];
        }
        //获取角色模块行为，相同则返回，不同则修改数据权限
        $role_module_action_model = new RoleModuleActionsModel();
        $role_module_action_model->setRoleId($role_id);
        $role_ordinal_module_actions = $role_module_action_model->getAllByRoleId();
        $ordinal_module_action_ids = [];
        foreach ($role_ordinal_module_actions as $value) {
            array_push($ordinal_module_action_ids, intval($value['module_action_id']));
        }
        $module_a_ids = array_keys($module_action_relations);
        //获取减少的模块的行为权限
        $reduce_module_a_ids = array_diff($ordinal_module_action_ids, $module_a_ids);

        //获取新增的模块的行为权限
        $add_module_a_ids = array_diff($module_a_ids, $ordinal_module_action_ids);

        //相同的模块
        $common_module_a_ids = array_intersect($module_a_ids, $ordinal_module_action_ids);

        $has_change = false;
        $role_module_action_model = new RoleModuleActionsModel();
        $role_module_action_model->setRoleId($role_id);
        if($common_module_a_ids){//关联模块的行为未变更
            //获取需启用关系的模块的行为
            $enable_module_a_ids = [];
            foreach($role_ordinal_module_actions as $value){
                if(intval($value['status']) == ConstantConfig::STATUS_DELETE and
                    in_array($value['module_action_id'], $common_module_a_ids)){
                    array_push($enable_module_a_ids, $value['module_action_id']);
                }
            }
            if($enable_module_a_ids){
                $role_module_action_model->setStatus(ConstantConfig::STATUS_DEFAULT);
                $role_module_action_model->setUpdatedAt($operate_time);
                if(!$role_module_action_model->updateStatusByModuleActionIds($enable_module_a_ids, $role_id)){
                    return ['success'=>false];
                }
                $has_change = true;
            }
        }
        //模块的行为权限减少，停用关系
        if($reduce_module_a_ids){
            $role_module_action_model->setStatus(ConstantConfig::STATUS_DELETE);
            $role_module_action_model->setUpdatedAt($operate_time);
            if(!$role_module_action_model->updateStatusByModuleActionIds($reduce_module_a_ids, $role_id)){
                return ['success'=>false];
            }
            $has_change = true;
        }

        //新增功能权限
        if($add_module_a_ids){
            $command = $role_module_action_model->createBatch();
            $status = ConstantConfig::STATUS_DEFAULT;
            foreach ($add_module_a_ids as $s_id) {
                $role_module_action_model->setRoleId($role_id);
                $role_module_action_model->setModuleActionId($s_id);
                $role_module_action_model->setModuleId($module_action_relations[$s_id][0]);
                $role_module_action_model->setActionId($module_action_relations[$s_id][1]);
                $role_module_action_model->setStatus($status);
                $role_module_action_model->setCreatedAt($operate_time);

                $role_module_action_model->createBatchExecute($command);
            }
            $has_change = true;
        }
        return ['success'=>true, 'has_change'=>$has_change];
    }

}