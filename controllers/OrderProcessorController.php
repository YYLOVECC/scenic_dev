<?php
/**
 * 订单任务处理类
 * User: teresa
 * Date: 8/28/15
 * Time: 3:26 PM
 */
namespace app\controllers;

use app\components\BaseController;
use Yii;

use app\services\func\OrderService;

class OrderProcessorController extends BaseController
{

    public $enableCsrfValidation = false;

    /**
     * 订单完成退款
     * @return string
     */
    public function actionCompleteRefundOrder()
    {
        $request = Yii::$app->request;
        $order_ids = $request->post('order_ids');
        if(empty($order_ids)){
            return json_encode(['success'=>false, 'msg'=>'参数传递错误']);
        }
        $ids = explode(',', $order_ids);
        $order_service = new OrderService();
        $res = $order_service->completeRefund($ids);
        return json_encode($res);
    }

    /**
     * 取消3小时后未支付订单
     */
    public function actionCancelUnpaidOrder()
    {
        $order_service = new OrderService();
        $res = $order_service->cancelUnpaidOrder();
        return json_encode($res);
    }
}