<?php
namespace app\services\func;

use app\models\RoleModuleActionsModel;
use app\models\RoleModulesModel;
use app\models\UserRolesModel;
use Exception;

use Yii;
use yii\web\Request;

use app\forms\ModulesForm;
use app\models\ModulesModel;
use app\models\ModuleActionsModel;
use app\models\ActionsModel;
use app\util\ConstantConfig;

class FeaturesAuthService
{
    /**
     * 新增数据
     * @param ModulesForm $form
     * @return bool
     */
    public function create(ModulesForm $form)
    {
        $modules_model = new ModulesModel();
        $modules_model->setName($form->name);
        $modules_model->setParentId($form->parent_id);
        $modules_model->setParentId($form->parent_id);
        $modules_model->setDescription($form->description);
        $modules_model->setPageUrl($form->page_url);
        $modules_model->setIsDisplay($form->is_display);
        $modules_model->setCreatedAt(Yii::$app->params['current_time']);
        $modules_model->setUpdatedAt(Yii::$app->params['current_time']);

        $connection = Yii::$app->db;
        $connection->open();

        $transaction = $connection->beginTransaction();
        try {
            $modules_model->create();
            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollBack();
            $connection->close();
            return false;
        }
        $connection->close();
        return true;
    }


    public function updateByPk(ModulesForm $form)
    {
        $modules_model = new ModulesModel();
        $modules_model->setId($form->id);
        $modules_model->setName($form->name);
        $modules_model->setPageUrl($form->page_url);
        $modules_model->setDescription($form->description);
        $modules_model->setIsDisplay($form->is_display);
        $modules_model->setParentId($form->parent_id);
        $current_time = Yii::$app->params['current_time'];
        $modules_model->setUpdatedAt($current_time);

        $connection = Yii::$app->db;
        $connection->open();

        $transaction = $connection->beginTransaction();
        try {
            //修改模块信息
            $modules_model->updateByPk();
            $transaction->commit();

//            //模块有改动，清除模块关联角色的权限信息
//            //获取模块管理角色
//            $role_module_model = new RoleModulesModel();
//            $role_module_model->setModuleId($form->id);
//            $role_modules = $role_module_model->getByModuleId();
//
//            //清除角色关联用户的权限缓存
//            if(!empty($role_modules)){
//                $role_ids = [];
//                foreach($role_modules as $v){
//                    array_push($role_ids, $v['role_id']);
//                }
//                $user_role_model = new UserRolesModel();
//                $role_users = $user_role_model->getByRoleIds($role_ids);
//                $user_ids = [];
//                foreach($role_users as $value){
//                    array_push($user_ids, $value['user_id']);
//                }
//                $user_ids = array_unique($user_ids);
////                foreach($user_ids as $u_id){
////                    RedisUtil::hdel(Yii::$app->params['privilege_name'], 'feature_privilege_'.$u_id);
////                    RedisUtil::hdel(Yii::$app->params['privilege_name'], 'user_left_menu_'.$u_id);
////                }
//            }

            //不显示模块时解绑模块关联角色
            if($form->is_display == ConstantConfig::ENABLE_FALSE){
                //模块关联角色解绑
                $role_module_model = new RoleModulesModel();
                $role_module_model->setModuleId($form->id);
                $role_module_model->setUpdatedAt($current_time);
                $role_module_model->setStatus(ConstantConfig::STATUS_DELETE);
                $role_module_model->updateStatusByMId();
            }

        } catch (Exception $e) {

            $transaction->rollBack();
            $connection->close();
            return false;
        }
        $connection->close();
        return true;
    }

    /**
     * 获取单条数数据
     * @param $id
     * @return array|bool
     */
    public function findByPk($id)
    {
        $connection = Yii::$app->db;
        $connection->open();

        $modules_model = new ModulesModel();
        $modules_model->setId($id);
        $module = $modules_model->findByPk();

        $connection->close();

        return $module;
    }

    public function listData(Request $request)
    {
        $page = $request->post('start', 0);
        $page_size = $request->post('page_size', 0);

        $modules_model = new ModulesModel();
        $modules_model->setId($request->post('id', null));
        $modules_model->setName($request->post('name', null));

        $num = $modules_model->countByPk();
        $features = $modules_model->findList($page, $page_size);

        //取出父级ID 并形成查询数组
        $id_array = [];
        foreach ($features as &$value) {
            $value['created_date'] = (int)$value['created_at'] ? date('Y/m/d H:i:s', (int)$value['created_at']) : '';
            if ($value['parent_id'] != 0) {
                array_push($id_array, $value['parent_id']);
            }
        }

        //获取模块名
        $names = [];
        if (!empty($id_array)) {
            $ids = implode(',', $id_array);
            $names_temp = $modules_model->findByIds($ids);

            foreach ($names_temp as $module) {
                $names[$module['id']] = $module['name'];
            }

        }

        return ['success' => true, 'count' => $num, 'data' => $features, 'names' => $names];
    }

    /**
     * 提交功能与行为数据
     * @param Request $request
     * @return bool
     */
    public function dialogSave(Request $request)
    {
        $ids = $request->post('data_ids','');
        $ids = explode(',', $ids);
        $module_id = $request->post('module_id',0);

        $module_actions_model = new ModuleActionsModel();
        $module_actions_model->setModuleId($module_id);

        $connection = Yii::$app->db;

        $transaction = $connection->beginTransaction();
        try {
            //查询功能所有的行为
            $original_module_actions = $module_actions_model->getAllActionsByModuleId();
            $original_actions_ids = [];
            foreach($original_module_actions as $value){
                array_push($original_actions_ids, $value['action_id']);
            }
            //获取减少的行为
            $reduce_action_ids = array_diff($original_actions_ids, $ids);

            //获取新增的行为
            $add_action_ids = array_diff($ids, $original_actions_ids);

            //相同的行为
            $common_action_ids = array_intersect($ids, $original_actions_ids);
            $current_time = Yii::$app->params['current_time'];
            if($common_action_ids){//行为未变更
                //获取需启用关系的行为
                $enable_action_ids = [];
                $enable_module_action_ids = [];
                foreach($original_module_actions as $value){
                    if((int)$value['status'] == ConstantConfig::STATUS_DELETE and in_array($value['action_id'], $common_action_ids)){
                        array_push($enable_action_ids, $value['action_id']);
                        array_push($enable_module_action_ids, $value['id']);
                    }
                }
                if($enable_action_ids){
                    $module_actions_model->setStatus(ConstantConfig::STATUS_DEFAULT);
                    $module_actions_model->setUpdatedAt($current_time);
                    if(!$module_actions_model->updateStatusByActionIds($enable_action_ids)){
                        throw new Exception;
                    }
                }
            }
            //行为减少，停用关系
            if($reduce_action_ids){
                //获取需停用关系的行为
                $disable_action_ids = [];
                $disable_module_action_ids = [];
                foreach($original_module_actions as $value){
                    if((int)$value['status'] == ConstantConfig::STATUS_DEFAULT and
                        in_array($value['action_id'], $reduce_action_ids)){
                        array_push($disable_action_ids, $value['action_id']);
                        array_push($disable_module_action_ids, $value['id']);
                    }
                }
                //停用模块行为
                if($disable_action_ids){
                    $module_actions_model->setStatus(ConstantConfig::STATUS_DELETE);
                    $module_actions_model->setUpdatedAt($current_time);
                    if(!$module_actions_model->updateStatusByActionIds($disable_action_ids)){
                        throw new Exception;
                    }
                }
                //停用角色模块行为
                $role_module_action_model = new RoleModuleActionsModel();
                $role_module_action_model->setStatus(ConstantConfig::STATUS_DELETE);
                $role_module_action_model->updateStatusByModuleActionIds($disable_module_action_ids);
            }

            //新增行为权限
            if($add_action_ids){
                $command = $module_actions_model->createBatch();
                $status = ConstantConfig::STATUS_DEFAULT;
                foreach ($add_action_ids as $s_id) {
                    $module_actions_model->setModuleId($module_id);
                    $module_actions_model->setActionId($s_id);
                    $module_actions_model->setStatus($status);
                    $module_actions_model->setCreatedAt($current_time);

                    $module_actions_model->createBatchExecute($command);
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
     * 获取弹出层需要的 行为数据 以及模块、行为关系数据
     * @param $module_id
     * @return array
     */
    public function dialogList($module_id)
    {
        $actions_model = new ActionsModel();
        $actions = $actions_model->getValidActions();

        $module_actions_model = new ModuleActionsModel();
        $module_actions_model->setModuleId($module_id);
        $module_actions = $module_actions_model->findByModuleId();

        $ids_array = [];
        foreach($module_actions as $value){
            array_push($ids_array,(int)$value['action_id']);
        }
        return ['actions' => $actions, 'module_actions' => $ids_array];
    }

    /**
     * 当前修改页面ID
     * @param int $id
     * @return array|mixed
     */
    public function tree($id = 0)
    {
        $modules_model = new ModulesModel();
        $data_temp = $modules_model->findAll($id);
        //定义遍历后的数组
        $data = [];
        foreach ($data_temp as $temp) {
            if ($temp['parent_id'] == 0) {
                array_push($data, $temp);
                $data = $this->_recursiveData($data, $data_temp, $temp);
            }
        }
        $data = array_reverse($data);
        return $data;
    }

    /**
     * 树形数据递归
     * @param $data
     * @param $data1
     * @param $temp
     * @return mixed
     */
    private function _recursiveData($data, $data1, $temp)
    {
        foreach ($data1 as $k => $v) {
            if ($temp['id'] == $v['parent_id']) {
                array_push($data, $v);
                unset($data1[$k]);
                $data = $this->_recursiveData($data, $data1, $v);
            }
        }

        return $data;
    }

    /**
     * 获取有效模块
     * @return array|null
     */
    public function getEnableModules()
    {
        //缓存读取
//        $modules = RedisUtil::get('valid_modules');
//        if ($modules) {
//            return json_decode($modules, true);
//        }
        //没有缓存，请求数据库
        try {
            $module_model = new ModulesModel();
            $modules = $module_model->getValidModules();
//            if(!empty($modules)){
//                //缓存模块信息
//                RedisUtil::set('valid_modules', json_encode($modules), null, 7*24*60*60);
//            }
        } catch (Exception $e) {
            $modules = [];
        }

        return $modules;
    }


    /**
     * 获取有效模块行为
     * @return array|null
     */
    public function getEnableModuleActions()
    {
        $module_action_model = new ModuleActionsModel();
        return $module_actions = $module_action_model->getValidModuleActions();
    }

    /**
     * 获取有效行为
     * @return array|null
     */
    public function getEnableActions()
    {
        //缓存读取
//        $actions = RedisUtil::get('valid_actions');
//        if ($actions) {
//            return json_decode($actions, true);
//        }
        //没有缓存，请求数据库
        try {
            $action_model = new ActionsModel();
            $actions = $action_model->getValidActions();
//            if(!empty($actions)){
//                //缓存行为信息
//                RedisUtil::set('valid_actions', json_encode($actions), null, 7*24*60*60);
//            }

        } catch (Exception $e) {
            //todo 错误日志
            $actions = [];
        }

        return $actions;
    }
}