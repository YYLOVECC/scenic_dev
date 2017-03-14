<?php
namespace app\controllers;

use app\forms\AdminUsersForm;
use app\services\func\RoleService;
use app\services\role\CRoleService;
use Yii;
use yii\web\HttpException;

use app\components\SuperController;
use app\services\func\UsersService;

class UsersController extends SuperController
{

    public function init(){
        parent::init();

        //模块权限检测
        $module_id = $this->getModuleIdByUrl('/users');
        if(!$module_id){
            throw new HttpException(400);
        }

        $this->module_id = $module_id;
        if(!$this->checkModuleAccess($module_id)){
            throw new HttpException(400);
        }
    }

    /**
     * 用户管理首页
     * @return string
     */
    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        $role_service = new CRoleService();
        $roles = $role_service->getValidRoles();
        $this->scripts = ['js/users/list.js'];
        return $this->render('list.twig', ['roles'=>$roles, 'actions'=>$actions]);
    }

    /**
     * 用户管理 用户修改
     */
    public function actionEdit()
    {
        //操作权限检测
        if(!$this->checkModuleActionAccess($this->module_id, 'edit')){
            throw new HttpException(400);
        }

        $request = Yii::$app->request;

        $id = $request->getQueryParam('id', null);

        if ($id == null) {
            throw new HttpException(404);
        }

        //检索用户信息
        $user_service = new UsersService();
        $users = $user_service->findByPk($id);
        if(empty($users)){
            throw new HttpException(500, '用户信息不存在或已停用');
        }
        //获取用户角色
        $user_role_ids = $user_service->getRoleIdsById($id);
        //获取有效角色
        $role_service = new CRoleService();
        $valid_roles = $role_service->getValidRoles();

        //将数据导入form
        $admin_users_form = new AdminUsersForm();
        $admin_users_form->setScenario('edit');

        $admin_users_form->setAttributes($users, false);
        if(!empty($user_role_ids)){
            $admin_users_form->roles_id = implode(',', $user_role_ids);
        }

        //验证POST事件
        if ($request->getIsPost()) {
            $admin_users_form->setAttributes($request->post('AdminUsersForm'), false);

            if($admin_users_form->validate()){
                if($user_service->updateByPk($admin_users_form)){
                    return $this->redirect('/users');
                }
            }
        }

        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/validate.js', 'js/users/edit.js'];
        return $this->render('edit.twig', ['model' => $admin_users_form, 'user_role_ids'=>$user_role_ids,
            'valid_roles'=>$valid_roles]);
    }

    /**
     * 异步读取用户列表
     */
    public function actionAjaxUsersList()
    {
        $request = Yii::$app->request;
        $users_service = new UsersService();
        $data = $users_service->listData($request);

        return json_encode($data);

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
        //获取有效角色
        $role_service = new CRoleService();
        $valid_roles = $role_service->getValidRoles();
        //获取所有角色的name和email
        $user_service = new UsersService();
        $user_info = $user_service->getAllUserInfo();

        $admin_users_form = new AdminUsersForm();
        $admin_users_form->setScenario('add');

        //判断是否是post请求
        $request = Yii::$app->request;
        if ($request->getIsPost()) {
            $admin_users_form->setAttributes($request->post('AdminUsersForm'), false);
            if($admin_users_form->validate()){
                if ($user_service->addAdminUser($admin_users_form)) {
                    $this->redirect('/users');
                }
            }
        }
        $this->css = ['libs/ztree/css/zTreeStyle/zTreeStyle.css'];
        $this->scripts = ['libs/ztree/js/jquery.ztree.core-3.5.min.js', 'libs/ztree/js/jquery.ztree.excheck-3.5.min.js',
            'js/validate.js','js/users/add.js'];
        return $this->render('add.twig', ['user_form'=>$admin_users_form,'valid_roles'=>$valid_roles, 'user_info'=> $user_info ]);
    }
}