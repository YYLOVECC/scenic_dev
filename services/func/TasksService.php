<?php
namespace app\services\func;

use app\models\UserRolesModel;
use Exception;

use Yii;
use yii\web\Request;

use app\models\ActionsModel;
use app\forms\ActionsForm;
use app\util\ConstantConfig;
use app\models\ModuleActionsModel;
use app\models\RoleModuleActionsModel;
use app\util\RedisUtil;

class TasksService
{
    public function listData(Request $request)
    {
        $actions_model = new ActionsModel();

        $page = $request->post('start', 0);
        $page_size = $request->post('page_size', 0);

        $connection = Yii::$app->db;
        $connection->open();

        $num = $actions_model->countByPk();
        $tasks = $actions_model->findList($page, $page_size);
        foreach($tasks as &$value){
            $value['created_date'] = (int)$value['created_at'] ? date('Y/m/d H:i:s', (int)$value['created_at']) : '';
        }

        $connection->close();

        return ['success' => true, 'count' => $num, 'data' => $tasks];
    }

    public function findByPk($id)
    {
        $connection = Yii::$app->db;
        $connection->open();

        $actions_model = new ActionsModel();
        $actions_model->setId($id);
        $actions = $actions_model->findByPk();

        $connection->close();

        return $actions;
    }

    public function updateByPk(ActionsForm $form)
    {
        $actions_model = new ActionsModel();
        $actions_model->setId($form->id);
        $actions_model->setName($form->name);
        $actions_model->setEName($form->e_name);
        $actions_model->setDescription($form->description);
        $actions_model->setUpdatedAt(Yii::$app->params['current_time']);

        $connection = Yii::$app->db;
        $connection->open();

        $transaction = $connection->beginTransaction();
        try {
            $actions_model->updateByPk();
            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollBack();
            $connection->close();
            return false;
        }

        //清除行为缓存
        RedisUtil::del('valid_actions');
        $connection->close();
        return true;
    }

    public function create(ActionsForm $form)
    {
        $actions_model = new ActionsModel();
        $actions_model->setName($form->name);
        $actions_model->setEName($form->e_name);
        $actions_model->setDescription($form->description);
        $actions_model->setIsEnable(ConstantConfig::ENABLE_TRUE);
        $actions_model->setCreatedAt(Yii::$app->params['current_time']);
        $actions_model->setUpdatedAt(Yii::$app->params['current_time']);

        $connection = Yii::$app->db;

        $transaction = $connection->beginTransaction();
        try {
            $actions_model->create();

            //清除行为缓存
            RedisUtil::del('valid_actions');
            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }

        return true;
    }

    /**
     * 异步修改是否启用状态
     * 停用行为则停用相关权限，启用时不修改权限关系
     * @param Request $request
     * @return bool
     */
    public function ajaxEnable(Request $request){

        $is_enable = $request->post('is_enable',0);
        $id = $request->post('id',0);


        $connection = Yii::$app->db;

        $transaction = $connection->beginTransaction();
        try {
            //修改行为停启用状态
            $actions_model = new ActionsModel();
            $actions_model->setUpdatedAt(Yii::$app->params['current_time']);
            $actions_model->setId($id);
            $actions_model->setIsEnable($is_enable);
            $actions_model->updateIsEnableByPk();

            //停用操作
            if($is_enable == ConstantConfig::ENABLE_FALSE){
                //停用模块操作关系
                $module_actions_model = new ModuleActionsModel();
                $module_actions_model->setActionId($id);
                $module_actions_model->setUpdatedAt(Yii::$app->params['current_time']);
                $module_actions_model->setStatus(ConstantConfig::STATUS_DELETE);
                $module_actions_model->updateStatusByActionId();

                $role_module_actions_model = new RoleModuleActionsModel();
                $role_module_actions_model->setActionId($id);
                //获取action关联角色信息
                $role_module_actions = $role_module_actions_model->getByActionId();
                //停用角色模块操作关系
                $role_module_actions_model->setStatus(ConstantConfig::STATUS_DELETE);
                $role_module_actions_model->setUpdatedAt(Yii::$app->params['current_time']);
                $role_module_actions_model->updateStatusByActionId();

                //清除用户功能权限
                if(!empty($role_module_actions)){
                    $update_role_ids = [];
                    foreach($role_module_actions as $value){
                        array_push($update_role_ids, $value['role_id']);
                    }
                    //清除角色关联用户的功能权限信息
                    $user_role_model = new UserRolesModel();
                    $role_users = $user_role_model->getByRoleIds($update_role_ids);
                    $user_ids = [];
                    foreach($role_users as $value){
                        array_push($user_ids, $value['user_id']);
                    }
                    $user_ids = array_unique($user_ids);
                    foreach($user_ids as $u_id){
                        RedisUtil::hdel(Yii::$app->params['popedom_name'], 'feature_popedom_'.$u_id);
                    }

                }
            }

            $transaction->commit();

            //清除有效行为权限
            RedisUtil::del('valid_actions');

        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }

        return true;
    }
}