<?php
/**
 * 角色控制器
 */

namespace app\controllers;

use app\util\ConstantConfig;
use Yii;
use app\components\SuperController;
use app\forms\RoleForm;
use app\services\func\FeaturesAuthService;
use app\services\role\CRoleService;
use yii\web\HttpException;

class RoleController extends SuperController
{

    public function init(){
        parent::init();
        //模块权限检测
        $module_id = $this->getModuleIdByUrl('/role');
        if(!$module_id){
            throw new HttpException(400);
        }
        $this->module_id = $module_id;
        if(!$this->checkModuleAccess($module_id)){
            throw new HttpException(400);
        }
    }


    /**
     * 角色首页
     * @return string
     */
    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/role/list.js'];
        return $this->render('list.twig', ['actions'=>$actions]);
    }

    /**
     * ajax请求角色列表
     */
    public function actionAjaxRoleList()
    {
        $request = Yii::$app->request;
        $role_name = $request->post('search_name', '');
        $state = $request->post('state', ConstantConfig::ENABLE_ALL);
        $start = (int)$request->post('start', 0);
        $page_size = (int)$request->post('page_size', 20);
        $ordinal_str = $request->post('ordinal_str', '');
        $ordinal_type = $request->post('ordinal_type', '');
        $c_role_service = new CRoleService();
        $query = ['role_name'=>$role_name, 'state'=>$state];
        $result = $c_role_service->searchRoleList($query, $start, $page_size, $ordinal_str, $ordinal_type);
        return json_encode($result);
    }


    /**
     * 新增角色
     * @return string|\yii\web\Response
     * @throws HttpException
     */
    public function actionAdd()
    {
        //新增操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'add')){
            throw new HttpException(400);
        }

        $role_form = new RoleForm();
        $role_form->setScenario('add');

        //判断是否是post请求
        $request = Yii::$app->request;
        if($request->isPost){
            $role_service = new CRoleService();
            //获取post参数
            $formData = $request->post('RoleForm');
            $is_parent = $request->post('is_parent', 0);
            $valid = true;
            #获取当前新增角色的level及上级角色信息
            if(!$is_parent){
                $formData['parent_id'] = 0;
                $formData['level'] = 1;
            }else{
                //获取父级角色信息
                $parent_role = $role_service->getById($formData['parent_id']);
                if(!$parent_role){
                    Yii::$app->session->setFlash('error', '上级角色信息不存在或已被删除');
                    $valid = false;
                }else{
                    $formData['level'] = $parent_role['level'] + 1;
                }
            }
            $role_form->setAttributes($formData);
            if($valid){
                if ($role_form->validate()) {
                    if($role_service->create($formData)){
                        return $this->redirect('/role');
                    }else{
                        Yii::$app->session->setFlash('error', '新增角色失败');
                    }
                }
            }
        }
        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/validate.js', 'js/role/edit.js'];
        return $this->render('add.twig', ['role_form'=>$role_form]);
    }

    /**
     * ajax请求有效角色
     * @return string
     */
    public function actionAjaxValidRoles()
    {
        $role_service = new CRoleService();
        $result = $role_service->getValidRoles();
        return json_encode(array('success'=>true, 'data'=>$result));
    }

    /**
     * 编辑角色
     */
    public function actionEdit()
    {
        //编辑权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'edit')){
            throw new HttpException(400);
        }

        //获取post参数
        $request = Yii::$app->request;
        $role_id = (int)$request->get('id', 0);
        if(!$role_id) {
            return $this->redirect('/role');
        }
        //获取角色信息
        $role_service = new CRoleService();
        $role = $role_service->getById($role_id);
        if(empty($role) || $role['is_enable'] == ConstantConfig::ENABLE_FALSE) {
            return $this->redirect('/role');
        }
        $role_form = new RoleForm();
        $role_form->setScenario('edit');

        //判断是否是post请求
        if($request->isPost){
            $formData = $request->post('RoleForm');
            $valid = true;
            $is_parent = $request->post('is_parent', 0);
            #获取当前修改角色的level及上级角色信息
            if(!$is_parent){
                $formData['parent_id'] = 0;
                $formData['level'] = 1;
            }else{
                $parent_role = $role_service->getById($formData['parent_id']);
                if(!$parent_role){
                    Yii::$app->session->setFlash('error', '上级角色信息不存在或已被删除');
                    $valid = false;
                }else{
                    $formData['level'] = $parent_role['level'] + 1;
                }
            }

            $formData['role_id'] = $role_id;
            $role_form->setAttributes($formData);
            if($valid){
                //验证成功开始修改
                if ($role_form->validate()) {
                    if($role_service->update($formData, $role)){
                        return $this->redirect('/role');
                    }else{
                        Yii::$app->session->setFlash('error', '编辑角色失败');
                    }
                }
            }

        }

        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/validate.js', 'js/role/edit.js'];
        $data = ['role_form'=>$role_form, 'role'=>$role];
        return $this->render('edit.twig', $data);
    }

    /**
     * 停用角色
     */
    public function actionAjaxDisableRole(){
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
        $role_id = (int)$request->post('id', 0);
        if(!$role_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：role_id']);
        }
        //处理角色停用
        $role_service = new CRoleService();
        $result = $role_service->disable($role_id);
        return json_encode($result);
    }


    /**
     * 启用角色
     */
    public function actionAjaxEnableRole(){
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
        $role_id = (int)$request->post('id', 0);
        if(!$role_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：role_id']);
        }
        //处理角色启用
        $role_service = new CRoleService();
        $result = $role_service->enable($role_id);
        return json_encode($result);
    }

    /**
     * 删除角色
     */
    public function actionAjaxDeleteRole(){
        //删除操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'delete')){
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        //获取post参数
        $request = Yii::$app->request;
        $role_id = (int)$request->post('id', 0);
        if(!$role_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：role_id']);
        }
        $role_service = new CRoleService();
        $result = $role_service->delete($role_id);
        return json_encode($result);
    }

    /**
     * 获取角色的功能权限
     */
    public function actionAjaxRoleFeaturePrivilege()
    {
        //编辑功能权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'edit_feature')){
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        //获取请求角色
        $request = Yii::$app->request;
        $role_id = (int)$request->post('id', 0);
        if(!$role_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：role_id']);
        }
        //获取所有有效模块和行为
        $feature_service = new FeaturesAuthService();
        $valid_modules = $feature_service->getEnableModules();
        $modules = [];
        foreach($valid_modules as $value){
            $value['actions'] = [];
            $modules[$value['id']] = $value;
        }

        $valid_actions = $feature_service->getEnableActions();
        $actions = [];
        foreach($valid_actions as $value){
            $actions[$value['id']] = $value;
        }

        $valid_module_actions = $feature_service->getEnableModuleActions();
        //获取有效模块的行为
        if($valid_module_actions){
            foreach($valid_module_actions as $value){
                if(array_key_exists($value['module_id'], $modules)){
                    $actions[$value['action_id']]['module_action_id'] = $value['id'];
                    array_push($modules[$value['module_id']]['actions'], $actions[$value['action_id']]);
                }
            }
        }
        //获取角色的模块
        $role_service = new CRoleService();
        $role_modules = $role_service->getRoleModules($role_id);
        $role_module_ids = [];
        foreach($role_modules as $value){
            array_push($role_module_ids, $value['module_id']);
        }
        //获取角色模块的行为
        $role_module_actions = $role_service->getRoleModuleActions($role_id);
        $role_module_action_ids = [];
        foreach($role_module_actions as $value){
            array_push($role_module_action_ids, $value['module_id'].'_'.$value['action_id'].'_'.$value['module_action_id']);
        }
        $role_module_ids = array_merge($role_module_ids, $role_module_action_ids);

        $modules = array_values($modules);
        return json_encode(array('success'=>true, 'modules'=>$modules, 'role_module_ids'=>$role_module_ids));
    }

    /**
     * 保存角色功能权限编辑
     */
    public function actionAjaxSaveRoleFeaturePrivilege()
    {
        //编辑功能权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'edit_feature')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        //获取请求参数
        $request = Yii::$app->request;
        $role_id = (int)$request->post('id', 0);
        if(!$role_id){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误：role_id']);
        }
        //获取数据权限ids
        $feature_id_str = $request->post('feature_id_str', '');

        if ($feature_id_str == "") {
            return json_encode(['success'=>true, 'msg'=>'功能权限更新成功']);
        } else {
            $feature_ids = explode(',', $feature_id_str);

            $role_service = new CRoleService();
            //编辑功能权限
            $result = $role_service->updateRoleFeaturePrivilege($role_id, $feature_ids);
            return json_encode($result);
        }
    }

    /**
     * 指定角色的用户列表
     */
    public function actionUserList()
    {
        //获取post参数
        $request = Yii::$app->request;
        $role_id = (int)$request->get('id', 0);
        if(!$role_id) {
            return $this->redirect('/role');
        }
        $role_service = new CRoleService();
        $role_info = $role_service->getById($role_id);
        if(empty($role_info)){
            return $this->redirect('/role');
        }
        $this->scripts = ['js/underscore-min.js'];
        return $this->render('user_list.twig', ['role'=>$role_info]);
    }

    /**
     * ajax请求角色用户列表
     */
    public function actionAjaxUserList()
    {
        $request = Yii::$app->request;

        //获取请求参数
        $role_id = (int)$request->post('role_id', 0);
        if(!$role_id){
            return json_encode(['success'=>false, 'count'=>0, 'data'=>[]]);
        }
        $user_id = (int)$request->post('user_id', 0);
        $user_name = $request->post('user_name', '');
        $user_state = (int)$request->post('user_state', ConstantConfig::ENABLE_ALL);
        $start = (int)$request->post('start', 0);
        $page_size = (int)$request->post('page_size', 20);
        $ordinal_str = $request->post('ordinal_str', '');
        $ordinal_type = $request->post('ordinal_type', '');
        $c_role_service = new CRoleService();
        $query = ['user_id'=>$user_id, 'user_name'=>$user_name,'user_state'=>$user_state];
        //分页查询角色用户信息
        $result = $c_role_service->searchRoleUserList($role_id, $query, $start, $page_size, $ordinal_str, $ordinal_type);
        return json_encode($result);
    }
}
