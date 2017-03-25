<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 7/30/15
 * Time: 11:27 AM
 */

namespace app\services\order;

use app\models\OrderDetailsModel;
use app\models\OrderInfoModel;
use app\models\OrderPaymentDetailsModel;
use app\services\super\CLogService;
use app\util\ConstantConfig;
use Yii;
use yii\base\Exception;

class COrderService {
    /**
     * 验证订单完成退款状态
     * @param $order_ids
     * @return array
     */
    public function validateCompleteRefund($order_ids){
        $order_info_dict = $this->getOrderInfoDict($order_ids);
        if(empty($order_info_dict)){
            return [];
        }

        $y_array = [];
        $n_array = [];
        foreach($order_info_dict as $order_id=>$order_info){
            $order_status = $order_info['order_status'];
            $pay_status = $order_info['pay_status'];
            if($pay_status==ConstantConfig::PAY_STATUS_REFUNDING && $order_status!=ConstantConfig::ORDER_STATUS_CANCEL){
                array_push($y_array, $order_id);
            }else{
                array_push($n_array, $order_id);
            }
        }
        return ['y'=>$y_array, 'n'=>$n_array];
    }
    /**
     * 活取订单信息
     * @param $order_ids
     * @return array|bool|null
     */
    public function getOrderInfoDict($order_ids){
        if (empty($order_ids)){
            return null;
        }
        $order_info_model = new OrderInfoModel();
        $order_info_list = $order_info_model->getOrderInfoList($order_ids);

        if(empty($order_info_list)){
            return null;
        }
        $order_dict = [];
        foreach($order_info_list as $order_info){
            $order_dict[$order_info['id']] = $order_info;
        }
        return $order_dict;
    }

    /**
     * 验证订单申请退款状态
     * @param $order_ids
     * @return array
     */
    public function validateApplyRefund($order_ids){
        $order_info_dict = $this->getOrderInfoDict($order_ids);
        if(empty($order_info_dict)){
            return [];
        }

        $y_array = [];
        $n_array = [];
        foreach($order_info_dict as $order_id=>$order_info){
            $order_status = $order_info['order_status'];
            $pay_status = $order_info['pay_status'];
//            $pay_type = $order_info['pay_type'];
            if($pay_status != ConstantConfig::PAY_STATUS_PAID){
                array_push($n_array, $order_id);
            }elseif (in_array($order_status, [ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION])){
                array_push($y_array, $order_id);
            }else{//订单状态错误，不能申请退款
                array_push($n_array, $order_id);
            }
        }
        return ['y'=>$y_array, 'n'=>$n_array];
    }
    /**
     * 得到订单的操作日志
     * @param $order_id
     * @return array
     */
    public function getOrderActionLogs($order_id){
        if(empty($order_id)){
            return ['success'=>false, 'msg'=>'参数传递错误'];
        }
        $clog_service = new CLogService();
        $logs_res = $clog_service->searchResourceActionLogs(ConstantConfig::RESOURCE_ORDER, $order_id);
        return $logs_res;
    }

    /**
     * 根据订单id/ids查询订单、订单明细
     * @param $order_id
     * @return array:
     */
    public function getOrderInfoWithDetails($order_id)
    {
        if(empty($order_id)){
            return [];
        }

        $return_data = [];
        $order_info_model = new OrderInfoModel();
        $order_payment_details_model = new OrderPaymentDetailsModel();
        $order_details_model = new OrderDetailsModel();
        if(is_array($order_id)){
            $orders = $order_info_model->getOrderInfoList($order_id);
            if(empty($orders)){
                return [];
            }
            //获取支付明细
            $order_payment_details = $order_payment_details_model->getOrderPaymentDetailsByOIds($order_id);
            $order_payment_details_dict = [];
            if (!empty($order_payment_details)) {
                foreach ($order_payment_details as $payment_detail) {
                    $p_order_id = intval($payment_detail['order_id']);
                    if (array_key_exists($p_order_id, $order_payment_details_dict)) {
                        $order_payment_details_dict[$p_order_id] = [];
                    }
                    $order_payment_details_dict[$p_order_id][] = $payment_detail;
                }
            }

            $details_dict = $order_details_model->getOrderDetailsByOrderIds($order_id);
            if(empty($details_dict)){
                return [];
            }
            $details = [];
            foreach($details_dict as $order_id => $o_details){
                $details = array_merge($details, $o_details);
            }
            foreach($orders as $item){
                $c_payment_details = array_key_exists(intval($item['id']), $order_payment_details_dict) ? $order_payment_details_dict[intval($item['id'])] : [];
                $c_details = array_key_exists((string)$item['id'], $details_dict) ? $details_dict[(string)$item['id']] : [];
                $return_data[] = ['order_info' => $item,
                    'payment_details' => $c_payment_details,
                    'details' => $c_details,];
            }
        }else{
            //订单信息
            $order_info_model->setId($order_id);
            $order_info = $order_info_model->getOrderInfo();
            if(empty($order_info)){
                return [];
            }
            //支付明细
            $order_payment_details = $order_payment_details_model->getOrderPaymentDetails($order_id);
            //订单明细
            $details = $order_details_model->getOrderDetails($order_id);
            if(empty($details)){
                $return_data[] = ['order_info' => $order_info, 'details' => []];
            }
            $return_data[] = ['order_info' => $order_info,
                'payment_details' => $order_payment_details,
                'details' => $details];
        }
        return $return_data;
    }


    /**
     * 获取支付明细
     * @param $order_id
     * @return array
     */
    public function getOrderPaymentDetails($order_id)
    {
        if (empty($order_id)) {
            return [];
        }

        $order_payment_details_model = new OrderPaymentDetailsModel();
        $order_payment_details = $order_payment_details_model->getOrderPaymentDetails($order_id);
        if (!empty($order_payment_details)) {
            $pay_type_arr = ConstantConfig::payTypeStr();
            $remit_pay_mode_arr = ConstantConfig::remitPayModeArr();
            $online_pay_mode_arr = ConstantConfig::onlinePayModeArr();
            $outside_pay_mode_arr = ConstantConfig::outsidePayModeArr();
            foreach ($order_payment_details as &$payment_detail) {
                $c_pay_type = intval($payment_detail['pay_type']);
                $payment_detail['pay_type_str'] = $pay_type_arr[$c_pay_type];
                $c_pay_mode = intval($payment_detail['pay_mode']);
                $c_pay_mode_str = '';
                switch ($c_pay_type) {
                    case ConstantConfig::PAY_TYPE_REMIT_OFFLINE:
                        $c_pay_mode_str = array_key_exists($c_pay_mode, $remit_pay_mode_arr) ? $remit_pay_mode_arr[$c_pay_mode] : '未知';
                        break;
                    case ConstantConfig::PAY_TYPE_ONLINE:
                        $c_pay_mode_str = array_key_exists($c_pay_mode, $online_pay_mode_arr) ? $online_pay_mode_arr[$c_pay_mode] : '未知';
                        break;
                    case ConstantConfig::PAY_TYPE_OUTSIDE:
                        $c_pay_mode_str = array_key_exists($c_pay_mode, $outside_pay_mode_arr) ? $outside_pay_mode_arr[$c_pay_mode] : '未知';
                        break;
                }
                $payment_detail['pay_mode_str'] = $c_pay_mode_str;
                $payment_detail['pay_at_str'] = $payment_detail['pay_at']?date('Y/m/d H:i:s', intval($payment_detail['pay_at'])):'';;
            }
        }
        return $order_payment_details;
    }
}