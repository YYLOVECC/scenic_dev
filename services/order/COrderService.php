<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 7/30/15
 * Time: 11:27 AM
 */

namespace app\services\order;

use app\components\SiteConfig;
use app\models\BaseMerchandiseInfoModel;
use app\models\BaseMerchandiseSpecificationsModel;
use app\models\MerchandiseInfoModel;
use app\models\MerchandiseSpecificationsModel;
use app\models\OrderDetailsModel;
use app\models\OrderGroupedDetailsModel;
use app\models\OrderInfoModel;
use app\models\OperationsModel;
use app\models\OperationItemsModel;
use app\models\OrderPaymentDetailsModel;
use app\services\store\CMerchandiseService;
use app\services\super\CLogService;
use app\util\ArrayUtil;
use app\util\ConstantConfig;
use app\util\StringUtil;
use Setting;
use Yii;
use app\util\CutWordsClient;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class COrderService {
    /**
     * 得到订单状态的中文解释
     * @param $order_info
     * @return string
     */
    public function getOrderStatusStr($order_info){
        if(empty($order_info)){
            return '空';
        }
        $pay_type = $order_info['pay_type'];
        $order_status = $order_info['order_status'];
        $shipping_status = $order_info['shipping_status'];
//        $pay_status = $order_info['pay_status'];
        if($pay_type == ConstantConfig::PAY_TYPE_ONLINE && $order_status==ConstantConfig::ORDER_STATUS_DEFAULT){
            $str = ConstantConfig::ORDER_STATUS_STR_WAITING_FOR_PAYMENT;
        }elseif($order_status == ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION){
            $str = ConstantConfig::ORDER_STATUS_STR_WAITING_FOR_CONFIRMATION;
        }elseif($order_status == ConstantConfig::ORDER_STATUS_WAITING_FOR_DELIVERY && $shipping_status == ConstantConfig::SHIPPING_STATUS_NON_DELIVERY){
            $str = ConstantConfig::ORDER_STATUS_STR_WAITING_FOR_DELIVERY;
        }elseif($order_status == ConstantConfig::ORDER_STATUS_WAITING_FOR_RECEIVE && $shipping_status == ConstantConfig::SHIPPING_STATUS_DISTRIBUTION){
            $str = ConstantConfig::ORDER_STATUS_STR_WAITING_FOR_RECEIVE;
        }elseif($order_status == ConstantConfig::ORDER_STATUS_COMPLETE){
            $str = ConstantConfig::ORDER_STATUS_STR_COMPLETE;
        }elseif($order_status == ConstantConfig::ORDER_STATUS_CANCEL){
            $str = ConstantConfig::ORDER_STATUS_STR_CANCEL;
        }else{
            $str = '未知';
        }
        return $str;
    }

    /**
     * 得到订单的支付价格
     * @param $order_info
     * @return float|int
     */
    public function getAllPayPrice($order_info){
        if(empty($order_info)){
            return 0;
        }
        $exchange_rate = $order_info['exchange_rate']; //币种汇率
        $original_price = $order_info['original_price']; //商品总额
        $shipping_fee = $order_info['shipping_fee'];//物流费用
        $insure_fee = $order_info['insure_fee'];//保险费用
        $red_envelope = $order_info['red_envelope']; //红包金额
        $bonus = $order_info['bonus']; //让利金额
        $distribution_price = $order_info['distribution_price']; //分销价
        //订单支付金额 = 商品总额 * 币种汇率 + 物流费用 + 分销价 - 红包 - 让利
        $all_price = round(floatval($original_price) * floatval($exchange_rate) + floatval($shipping_fee) + floatval($insure_fee)
            + floatval($distribution_price) - floatval($red_envelope) - floatval($bonus), 2);
        return $all_price;
    }

    /**
     * 得到订单的总金额
     * @param $order_info
     * @return float|int
     */
    public function getAllPrice($order_info){
        if(empty($order_info)){
            return 0;
        }
        $original_price = floatval($order_info['original_price']); //商品总额
        $shipping_fee = floatval($order_info['shipping_fee']);//物流费用
        $insure_fee = floatval($order_info['insure_fee']);//保险费用
        $exchange_rate = floatval($order_info['exchange_rate']);//保险费用
        //订单总额 = 商品总额 * 币种汇率 + 物流费用 + 保险费用
        $all_price = round($original_price * $exchange_rate + floatval($shipping_fee) + floatval($insure_fee), 2);
        return $all_price;
    }


    /**
     * 验证订单是否能够被取消
     * @param $order_ids
     * @return array
     */
    public function validateOrderCancel($order_ids){
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
            if($order_status==ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION ||
                $order_status==ConstantConfig::ORDER_STATUS_DEFAULT){//可以被取消
                if($pay_status == ConstantConfig::PAY_STATUS_UNPAID){
                    array_push($y_array, $order_id);
                }elseif($pay_status == ConstantConfig::PAY_STATUS_PAID){//该订单已经付款，不能作废，请进行退款操作
                    array_push($n_array, ['sn'=>$order_info['sn'], 'msg'=>'在线已支付订单不能作废，可进行退款操作']);
                }
            }else{
                array_push($n_array, ['sn'=>$order_info['sn'], 'msg'=>'订单状态有误']);
            }
        }
        return ['y'=>$y_array, 'n'=>$n_array];
    }


    /**
     * 验证订单是否能够被反作废
     * @param $order_ids
     * @return array
     */
    public function orderAntiCancel($order_ids){
        $order_info_dict = $this->getOrderInfoDict($order_ids);
        if(empty($order_info_dict)){
            return [];
        }
        $y_d_array = [];//需修改为待付款状态的订单
        $y_c_array = [];//需修改为待确认状态的订单
        $n_array = [];
        foreach($order_info_dict as $order_id=>$order_info){
            $order_status = (int)$order_info['order_status'];
            if($order_status==ConstantConfig::ORDER_STATUS_CANCEL){//可以反作废
                //在线支付更为待付款（在线支付已走申请退款流程，支付状态更为未付款）
                if(in_array((int)$order_info['pay_type'],
                        [ConstantConfig::PAY_TYPE_ONLINE, ConstantConfig::PAY_TYPE_OUTSIDE])){
                    $y_d_array[] = $order_id;
                }else{
                    $y_c_array[] = $order_id;
                }
            }else{
                array_push($n_array, $order_id);
            }
        }
        return ['yd'=>$y_d_array, 'yc' => $y_c_array, 'n'=>$n_array];
    }

    /**
     * 获取订单信息
     * @param $order_ids
     * @return array|bool|null
     */
    public function getOrderInfoList($order_ids){
        if (empty($order_ids)){
            return null;
        }
        $order_info_model = new OrderInfoModel();
        $order_infos = $order_info_model->getOrderInfoList($order_ids);
        if(empty($order_infos)){
            return null;
        }
        return $order_infos;
    }

    /**
     * 根据id获取订单信息
     * @param $order_id
     * @return array|bool|null
     */
    public function getOrderInfo($order_id)
    {
        if(empty($order_id)){
            return null;
        }
        $order_info_model = new OrderInfoModel();
        $order_info_model->setId($order_id);
        return $order_info_model->getOrderInfo();
    }

    public function getOrderInfoBySn($order_sn)
    {
        if(empty($order_sn)){
            return null;
        }
        $order_info_model = new OrderInfoModel();
        $order_info_model->setSn($order_sn);
        return $order_info_model->getOrderInfoBySnOrRelatedSn();
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