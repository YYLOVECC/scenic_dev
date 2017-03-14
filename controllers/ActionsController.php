<?php
namespace app\controllers;

use app\util\ConstantConfig;
use Yii;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

use app\forms\ActionsForm;
use app\components\SuperController;
use app\services\func\ActionsService;

class ActionsController extends SuperController
{
    public function init()
    {
        parent::init();

        //模块权限检测
        $module_id = $this->getModuleIdByUrl('/actions');

        if (!$module_id) {
            throw new HttpException(400);
        }

        $this->module_id = $module_id;
        if (!$this->checkModuleAccess($module_id)) {
            throw new HttpException(400);
        }
    }

    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        $this->scripts = ['js/underscore-min.js', 'js/actions/list.js'];
        return $this->render('list.twig', ['actions'=>$actions]);
    }

    /**
     * 异步读取行为数据
     */
    public function actionAjaxList()
    {
        $request = Yii::$app->request;
        $users_service = new ActionsService();
        $data = $users_service->listData($request);

        return json_encode($data);
    }

    public function actionCreate()
    {
        //新增权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'add')) {
            throw new HttpException(400);
        }
        $request = Yii::$app->request;

        $actions_form = new ActionsForm();

        //验证POST事件
        if ($request->getIsPost()) {

            $actions_form->setAttributes($request->post('ActionsForm'), false);

            $tasks_service = new ActionsService();
            $conditions = $actions_form->validate() && $tasks_service->create($actions_form);

            if ($conditions) {
                return $this->redirect('/actions');
            }
        }

        $title = '新增行为';
        $status = 'add';
        $this->scripts = ['js/validate.js'];
        return $this->render('modify.twig', ['model' => $actions_form, 'title' => $title, 'status' => $status]);
    }

    public function actionModify()
    {
        //编辑权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'edit')) {
            throw new HttpException(400);
        }

        $request = Yii::$app->request;

        $id = $request->getQueryParam('id', null);

        if ($id == null) {
            throw new NotFoundHttpException();
        }

        $actions_service = new ActionsService();
        $tasks = $actions_service->findByPk($id);

        $actions_form = new ActionsForm();
        $actions_form->setAttributes($tasks, false);

        //验证POST事件
        if ($request->getIsPost()) {

            $actions_form->setAttributes($request->post('ActionsForm'), false);

            $conditions = $actions_form->validate() && $actions_service->updateByPk($actions_form);
            if ($conditions) {
                return $this->redirect('/actions');
            }
        }

        $title = '修改行为';
        $status = 'update';
        $this->scripts = ['js/validate.js'];
        return $this->render('modify.twig', ['model' => $actions_form, 'title' => $title, 'status' => $status]);
    }

    /**
     * 停启用行为
     * @return string
     * @throws HttpException
     */
    public function actionAjaxEnable()
    {
        $request = Yii::$app->request;

        if ($request->getIsAjax() && $request->getIsPost()) {
            $is_enable = $request->post('is_enable', 0);
            if ($is_enable == ConstantConfig::ENABLE_FALSE) { //停用
                $action = 'disable';
                $action_str = '停用';
            } else { //启用
                $action = 'enable';
                $action_str = '启用';
            }
            //停启用权限检测
            if (!$this->checkModuleActionAccess($this->module_id, $action)) {
                if (Yii::$app->request->isAjax) {
                    return json_encode(["success" => false, "msg" => "无权限操作"]);
                } else {
                    throw new HttpException(400);
                }
            }

            $action_service = new ActionsService();
            $result = $action_service->ajaxEnable($request);

            if ($result) {
                return json_encode(['success'=>true, 'msg'=>$action_str. '操作成功']);
            } else {
                return json_encode(['success'=>true, 'msg'=>$action_str. '操作失败']);
            }

        } else {
            return json_encode(['success'=>false, 'msg'=>'非法请求']);
        }
    }
}
