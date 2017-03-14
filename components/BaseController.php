<?php
/**
 * 订单系统父类
 */

namespace app\components;

use Yii;
use yii\web\Controller;


class BaseController extends Controller
{
    /**
     * 复写请求结束后的操作方法
     * @param \yii\base\Action $action
     * @param mixed $result
     * @return mixed
     */
    public function afterAction($action, $result)
    {
        $connection = Yii::$app->db;
        $connection->close();
        return $result;
    }

}