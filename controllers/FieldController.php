<?php
/**
 * 字段权限控制类
 */

namespace app\controllers;

use Yii;
use yii\web\HttpException;

use app\forms\FieldForm;
use app\services\field\CFieldService;
use app\util\ConstantConfig;
use app\components\SuperController;

class FieldController extends SuperController
{
    public function init()
    {
        parent::init();
        //模块权限检测
        //模块权限检测
        $module_id = $this->getModuleIdByUrl('/field');
        if(!$module_id){
            throw new HttpException(400);
        }
        $this->module_id = $module_id;
        if(!$this->checkModuleAccess($module_id)){
            throw new HttpException(400);
        }
    }

    /**
     * 首页
     * @return string
     */
    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        return $this->render('list.twig', ['actions'=>$actions]);
    }

    /**
     * ajax请求字段权限列表
     */
    public function actionAjaxList()
    {
        //获取请求参数
        $request = Yii::$app->request;
        $field_name = $request->post('search_name', '');
        $status = $request->post('status', ConstantConfig::STATUS_ALL);
        $start = (int)$request->post('start', 0);
        $page_size = (int)$request->post('page_size', 20);
        $ordinal_str = $request->post('ordinal_str', '');
        $ordinal_type = $request->post('ordinal_type', '');
        $c_field_service = new CFieldService();
        $query = ['field_name'=>$field_name, 'status'=>$status];
        //分页获取检索数据
        $result = $c_field_service->searchFieldList($query, $start, $page_size, $ordinal_str, $ordinal_type);
        return json_encode($result);
    }


    /**
     * 新增字段
     */
    public function actionAdd()
    {
        //操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'add')){
            throw new HttpException(400);
        }

        $field_form = new FieldForm();
        $field_form->setScenario('add');

        //判断是否是post请求
        $request = Yii::$app->request;
        if($request->isPost){
            $c_field_service = new CFieldService();
            //获取post参数
            $formData = $request->post('FieldForm');

            $field_form->setAttributes($formData);
            //验证成功开始添加
            if ($field_form->validate()) {
                if($c_field_service->create($formData)){
                    return $this->redirect('/field');
                }else{
                    Yii::$app->session->setFlash('error', '新增字段失败');
                }
            }

        }
        return $this->render('add.twig', ['field_form'=>$field_form]);
    }

    /**
     * 编辑字段权限
     */
    public function actionEdit()
    {
        //编辑操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'edit')){
            throw new HttpException(400);
        }

        //获取post参数
        $request = Yii::$app->request;
        $field_id = (int)$request->get('id', 0);
        if(!$field_id) {
            return $this->redirect('/field');
        }
        //获取字段权限信息
        $c_field_service = new CFieldService();
        $field_privilege = $c_field_service->getById($field_id);
        if(empty($field_privilege)) {
            return $this->redirect('/field');
        }

        $field_form = new FieldForm();
        $field_form->setScenario('edit');

        //判断是否是post请求
        if($request->isPost){
            $formData = $request->post('FieldForm');
            $formData['field_id'] = $field_id;
            $field_form->setAttributes($formData);

            //验证成功开始修改
            if ($field_form->validate()) {
                if($c_field_service->update($formData, $field_privilege)){
                    return $this->redirect('/field');
                }else{
                    Yii::$app->session->setFlash('error', '编辑字段权限失败');
                }
            }

        }

        $data = ['field_form'=>$field_form, 'field_privilege'=>$field_privilege];
        return $this->render('edit.twig', $data);
    }


    /**
     * 停用字段权限
     */
    public function actionAjaxDisable()
    {
        //停用操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'disable')){
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        //获取post参数
        $request = Yii::$app->request;
        $field_id = (int)$request->post('id', 0);
        if(!$field_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：field_id']);
        }
        //处理字段权限停用
        $c_field_service = new CFieldService();
        $result = $c_field_service->disable($field_id);
        return json_encode($result);
    }

    /**
     * 启用字段权限
     */
    public function actionAjaxEnable()
    {
        //启用操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'enable')){
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        //获取post参数
        $request = Yii::$app->request;
        $field_id = (int)$request->post('id', 0);
        if(!$field_id){
            return json_decode(['success'=>false, 'msg'=>'参数传递错误：field_id']);
        }
        //处理字段权限启用
        $c_field_service = new CFieldService();
        $result = $c_field_service->enable($field_id);
        return json_encode($result);
    }

}