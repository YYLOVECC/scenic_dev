<?php
namespace app\controllers;

use app\models\AdminUsersModel;
use Yii;
use yii\base\Exception;
use yii\helpers\Url;

use app\components\BaseController;
//use app\components\SiteConfig;

use app\util\YiiCookie;
use app\util\AESUtils;
use app\util\ConstantConfig;
use app\components\UserIdentity;

class SiteController extends BaseController
{
    public $layout = false;

    /**
     * 首页
     * @return string
     */
    public function actionIndex()
    {
        //检测是否登陆
        if(!UserIdentity::isLogin()){
            return $this->redirect(Url::to(['/site/login']));
        }
        return $this->render('index.twig', ['modules'=>UserIdentity::getUserLeftMenus()]);
    }

    /**
     * 登陆界面
     * @return string
     */
    public function actionLogin()
    {
        if (UserIdentity::isLogin()) {
            return $this->redirect(Url::to(['/']));
        }
        return $this->render('login.twig');
    }

    /**
     * 登录
     */
    public function actionAjaxLoginSave()
    {
        $request = Yii::$app->request;
        $email = $request->post('email','');
        $password = $request->post('password');
        $admin_user_model = new AdminUsersModel();
        $admin_user_model->setEmail($email);
        $user_info = $admin_user_model->findByEmail();
        if (empty($user_info)) {
            return json_encode(['success' => false, 'msg'=>'邮箱错误']);
        }
        $salt = $user_info['salt'];
        $get_password = md5(md5($password).$salt);
        $valid_password = $user_info['password'];
        if ($get_password != $valid_password) {
            return json_encode(['success' => false, 'msg'=>'密码错误']);
        }
        //管理员cookie
        $admin_token = ['user_id' => $user_info['id']];
        YiiCookie::set(ConstantConfig::ADMIN_COOKIE_NAME, $admin_token);
        return json_encode(['success' => true, 'msg'=>'登录成功']);
    }
    /**
     * 退出登陆
     * @return \yii\web\Response
     */
    public function actionLogout()
    {
        $cookie_name = ConstantConfig::ADMIN_COOKIE_NAME;
        //获取用户cookie
        $auth_token = YiiCookie::get($cookie_name);
        if(empty($auth_token)){
            return $this->redirect(Url::to(['/site/login']));
        }
        //清除cookie
        YiiCookie::delete(ConstantConfig::ADMIN_COOKIE_NAME);
        return $this->render('logout.twig');
    }

    /**
     * 修改密码界面
     */
    public function actionUpdatePassword()
    {
        return $this->render('update_password.twig');
    }

    /**
     * 密码保存
     * @return string
     * @throws Exception
     */
    public function actionAjaxPasswordSave()
    {   $cookie_name = ConstantConfig::ADMIN_COOKIE_NAME;
        //获取用户cookie
        $auth_token = YiiCookie::get($cookie_name);
        $user_id = $auth_token['user_id'];
        if (empty($user_id)) {
            return json_encode(['success'=>false, 'msg'=>'用户不存在']);
        }
        $admin_user_model = new AdminUsersModel();
        $admin_user_model->setId($user_id);
        $user_info = $admin_user_model->findByPk();
        $password = $user_info['password'];
        $salt = $user_info['salt'];
        $request = Yii::$app->request;
        $password_old = $request->post('password','');
        if (md5(md5($password_old).$salt) !=$password) {
            return json_encode(['success'=>false, 'msg'=>'原密码错误']);
        }
        $password_new = $request->post('password_new','');
        if ($password_new == $password_old) {
            return json_encode(['success'=>false, 'msg'=>'新密码不能与原密码相同']);
        }
        $salt_new = mt_rand(100000,999999);
        $password_new = md5(md5($password_new).$salt_new);
        $result = $admin_user_model-> updatePasswordById($password_new, $salt_new);
        if (!$result) {
            throw new Exception('修改密码失败');
        }
        //清除cookie
        YiiCookie::delete(ConstantConfig::ADMIN_COOKIE_NAME);
        return json_encode(['success'=> true, 'msg'=>'修改密码成功，请重新登录']);
    }

//    public function actionAjaxUserBehaviour()
//    {
//        $request = Yii::$app->request;
//        $login_id = $request->get('login_id');
//        $login_email = $request->get('login_email');
//        $login_name = $request->get('login_name');
//        $click_host = $request->get('click_host');
//        $click_text = $request->get('click_text');
//        $click_time = $request->get('click_time');
//
//        $user_behaviour_service = new UserBehaviourService();
//        $user_behaviour_service->save(['login_id' => $login_id, 'login_email' => $login_email, 'login_name' => $login_name,
//            'click_host' => $click_host, 'click_text' => $click_text, 'click_time' => $click_time]);
//        return json_encode(['success' => true]);
//    }

}