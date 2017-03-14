<?php
namespace app\controllers;

use Yii;
use yii\web\HttpException;

use app\components\SuperController;
use app\forms\ModulesForm;
use app\services\func\FeaturesAuthService;

class FeaturesAuthController extends SuperController
{
    public function init(){
        parent::init();

        //模块权限检测
        $module_id = $this->getModuleIdByUrl('/features-auth');

        if(!$module_id){
            throw new HttpException(400);
        }

        $this->module_id = $module_id;
        if(!$this->checkModuleAccess($module_id)){
            throw new HttpException(400);
        }
    }


    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        $this->scripts = ['js/underscore-min.js', 'js/features/list.js'];
        return $this->render('list.twig', ['actions'=>$actions]);
    }

    public function actionAjaxList()
    {
        $request = Yii::$app->request;

        $features_auth_service = new FeaturesAuthService();
        $data = $features_auth_service->listData($request);
        return json_encode($data);
    }

    public function actionAdd()
    {
        //新增权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'add')) {
            throw new HttpException(400);
        }

        $request = Yii::$app->request;

        $modules_form = new ModulesForm();

        if ($request->getIsPost()) {

            $modules_form->setAttributes($request->post('ModulesForm'), false);
            $features_auth_service = new FeaturesAuthService();
            $conditions = $modules_form->validate() && $features_auth_service->create($modules_form);

            if ($conditions) {
                return $this->redirect('/features-auth');
            }
        }
        $title = '新增权限';
        $status = 'add';
        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/validate.js', 'js/features/modify.js'];
        return $this->render('modify.twig', ['model' => $modules_form, 'title' => $title, 'status' => $status]);
    }

    public function actionEdit()
    {
        //编辑权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'edit')) {
            throw new HttpException(400);
        }

        $request = Yii::$app->request;

        $id = $request->getQueryParam('id', null);

        if ($id == null) {
            throw new HttpException(404);
        }

        $features_auth_service = new FeaturesAuthService();
        $module = $features_auth_service->findByPk($id);

        $modules_form = new ModulesForm();
        $modules_form->setAttributes($module, false);

        if ($request->getIsPost()) {
            $modules_form->setAttributes($request->post('ModulesForm'), false);

            $conditions = $modules_form->validate() && $features_auth_service->updateByPk($modules_form);
            if ($conditions) {
                return $this->redirect('/features-auth');
            }
        }

        $title = '修改权限';
        $status = 'update';
        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/validate.js', 'js/features/modify.js'];
        return $this->render('modify.twig', ['model' => $modules_form, 'title' => $title, 'status' => $status]);
    }

    /**
     * 调用树操作
     */
    public function actionAjaxTree()
    {
        $request = Yii::$app->request;

        $id = $request->post('id', 0);

        $features_auth_service = new FeaturesAuthService();
        $data = $features_auth_service->tree($id);
        return json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * 获取弹窗行为数据
     * @return string
     */
    public function actionAjaxDialogList()
    {
        $request = Yii::$app->request;
        $module_id = (int)$request->post('module_id', 0);

        if(!$request->getIsAjax()){
            return json_encode(['success' => false, 'msg' => '非正常请求']);
        }

        if (!$module_id) {
            return json_encode(['success' => false, 'msg' => '参数传递错误：module_id']);
        }
        $features_auth_service = new FeaturesAuthService();
        $data = $features_auth_service->dialogList($module_id);
        return json_encode(['success' => true, 'actions' => $data['actions'], 'module_actions_ids' => $data['module_actions']]);
    }

    public function actionAjaxDialogSave()
    {
        //编辑权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'edit')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        $request = Yii::$app->request;

        $role_id = (int)$request->post('module_id', 0);

        if(!$role_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：module_id']);
        }

        if($request->getIsAjax() && $request->getIsPost()){
            $features_auth_service = new FeaturesAuthService();
            $result = $features_auth_service->dialogSave($request);

            if($result){
                return json_encode(['success'=>true, 'msg'=>'保存成功']);
            }else{
                return json_encode(['success'=>false, 'msg'=>'保存失败']);
            }

        }else{
            return json_encode(['success'=>false, 'msg'=>'非法请求']);
        }
    }
}
