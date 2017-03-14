<?php
namespace app\services\func;

use app\components\SiteConfig;
use app\util\ConstantConfig;
use app\util\RedisUtil;
use Exception;

use Yii;
use yii\helpers\ArrayHelper;

use app\models\AdminUsersModel;
use app\models\LoginLogsModel;
use app\util\APIUtil;

class SsoService
{
    const enableUser = 1; //启用账号
    const disableUser = 0; //停用账号

    /**
     * 创建用户
     * @param $params
     * @return string
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function createUser($params)
    {
        $header = $this->_getHeader();
        $signature = ArrayHelper::getValue($header, 'signature');

        $data = Array(
            'app_key' => ArrayHelper::getValue($header, 'app_key'),
            'action' => ArrayHelper::getValue($params, 'action'),
            'user_name' => ArrayHelper::getValue($params, 'user_name'),
            'telephone' => ArrayHelper::getValue($params, 'telephone'),
            'current_date' => ArrayHelper::getValue($header, 'current_date'),
            'email' => ArrayHelper::getValue($params, 'email'),
        );

        if ($signature != APIUtil::getSignature($data, SiteConfig::get('sso_secret_key'))) {
            return json_encode(['code' => 403, 'msg' => '签名错误']);
        }

        $connection = Yii::$app->db;
        $connection->open();
        $transaction = $connection->beginTransaction();

        try {
            //检测用户邮箱是否已存在
            $model = new AdminUsersModel();
            $model->setEmail($data['email']);
            $user_info = $model->findByEmail();
            if(!empty($user_info)){
                return json_encode(['code' => 500, 'msg' => '用户邮箱已存在']);
            }
            //新增用户
            $model->setName($data['user_name']);
            $model->setIsEnable(ConstantConfig::ENABLE_TRUE);
            $model->setTelephone($data['telephone']);
            $model->setCreatedAt(Yii::$app->params['current_time']);
            $model->setUpdatedAt(Yii::$app->params['current_time']);
            $model->create();

            $transaction->commit();
        } catch (Exception $e) {

            $transaction->rollBack();
            return json_encode(['code' => 500, 'msg' => $e->getMessage()]);
        }
        $connection->close();

        return json_encode(['code' => 200, 'msg' => '创建用户成功']);

    }

    /**
     * 是否禁用账号
     * @param $params
     * @param $isEnable
     * @return string
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function isDisableUser($params, $isEnable)
    {
        $header = $this->_getHeader();
        $signature = ArrayHelper::getValue($header, 'signature');

        $data = [
            'app_key' => ArrayHelper::getValue($header, 'app_key'),
            'action' => ArrayHelper::getValue($params, 'action'),
            'user_name' => ArrayHelper::getValue($params, 'user_name'),
            'telephone' => ArrayHelper::getValue($params, 'telephone'),
            'current_date' => ArrayHelper::getValue($header, 'current_date'),
            'email' => ArrayHelper::getValue($params, 'email'),
        ];

        if ($signature != APIUtil::getSignature($data, SiteConfig::get('sso_secret_key'))) {
            return json_encode(['code' => 403, 'msg' => '签名错误']);
        }

        $connection = Yii::$app->db;
        $connection->open();
        $transaction = $connection->beginTransaction();

        try {

            $model = new AdminUsersModel();
            $model->setEmail($data['email']);
            $model->setIsEnable($isEnable);
            $model->isDisable();
            //清除用户活动区缓存
            RedisUtil::hdel(Yii::$app->params['sso_name'], $data['email']);
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            return json_encode(['code' => 500, 'msg' => $e->getMessage()]);
        }
        $connection->close();

        return json_encode(['code' => 200, 'msg' => '操作成功']);
    }

    /**
     * SSO 登陆接口
     * @param $params
     * @return bool
     */
    public function login($params)
    {
        $signature = ArrayHelper::getValue($params, 'signature');

        $data = Array(
            'app_key' => ArrayHelper::getValue($params, 'app_key'),
            'action' => ArrayHelper::getValue($params, 'action'),
            'user_name' => ArrayHelper::getValue($params, 'user_name'),
            'telephone' => ArrayHelper::getValue($params, 'telephone'),
            'current_date' => ArrayHelper::getValue($params, 'current_date'),
            'email' => ArrayHelper::getValue($params, 'email'),
        );

        if ($signature != APIUtil::getSignature($data, SiteConfig::get('sso_secret_key'))) {
            return ['code' => 403, 'msg' => '签名错误'];
        }

        $connection = Yii::$app->db;
        $connection->open();

        $model = new AdminUsersModel();
        $model->setEmail($data['email']);
        $result = $model->findByEmail();

        if(!$result){
            $connection->close();
            return ['code' => 404, 'msg' => '用户不存在'];
        }

        $transaction = $connection->beginTransaction();

        //将登陆记录到日志
        try {

            $request = Yii::$app->request;
            $ip = ip2long($request->getUserIP());
            $current_time = time();
            $user_id = $result['id'];

            //更新用户最新登陆信息
            $admin_user_model = new AdminUsersModel();
            $admin_user_model->setId($user_id);
            $admin_user_model->setLastLoginIp($ip);
            $admin_user_model->setLastLoginAt($current_time);
            $admin_user_model->updateLastLoginByPk();

            $model = new LoginLogsModel();
            $model->setUserId($result['id']);
            $model->setUserEmail($data['email']);
            $model->setUserName($data['user_name']);
            $model->setIp($ip);
            $model->setPort($request->getPort());
            $model->setCreatedAt($current_time);
            $model->create();

            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['code' => 500, 'msg' => $e->getMessage()];
        }

        $connection->close();

        if (!$result) {
            return ['code' => 404, 'msg' => '账号不存在'];
        }

        $expire_time = 24*60*60;//缓存1天
        RedisUtil::hmset(Yii::$app->params['sso_name'], $data['email'], Yii::$app->params['current_time'], null,
            $expire_time);

        return $result;
    }

    /**
     * SSO 退出接口
     * @param $params
     * @return string
     */
    public function logout($params)
    {

        $header = $this->_getHeader();
        $signature = ArrayHelper::getValue($header, 'signature');

        $data = Array(
            'app_key' => ArrayHelper::getValue($header, 'app_key'),
            'user_name' => ArrayHelper::getValue($params, 'user_name'),
            'telephone' => ArrayHelper::getValue($params, 'telephone'),
            'current_date' => ArrayHelper::getValue($header, 'current_date'),
            'email' => ArrayHelper::getValue($params, 'email'),
        );

        if ($signature != APIUtil::getSignature($data, SiteConfig::get('sso_secret_key'))) {
            return json_encode(['code' => 403, 'msg' => '签名错误', 'data' => $data]);
        }

        try {

            //清除用户活动区缓存
            RedisUtil::hdel(Yii::$app->params['sso_name'], $data['email']);

        } catch (Exception $e) {
            return json_encode(['code' => 500, 'msg' => 'redis删除存储失败', 'error' => $e->getMessage()]);
        }

        return json_encode([
            'code' => 200, 'msg' => '退出成功',
            'cookie' => ['key' => session_name(), 'value' => session_id()]
        ]);
    }


    private function _getHeader()
    {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if ('HTTP_' == substr($key, 0, 5)) {
                $key = strtolower(substr($key, 5));
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}