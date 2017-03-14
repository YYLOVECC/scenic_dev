<?php
/**
 * Created by PhpStorm.
 * User: teresa
 * Date: 8/27/15
 * Time: 4:38 PM
 */
namespace app\services\order;

use app\models\CustomerServiceDetailsModel;
use app\models\OrderRefundModel;
use app\util\ConstantConfig;
use Yii;
use app\models\CustomerServiceModel;
use yii\base\Exception;

class CCustomerService
{

    /**
     * 根据id查询退换货单信息
     * @param $c_id
     * @return array|bool|null
     */
    public function findByPk($c_id)
    {
        if(empty($c_id)){
            return null;
        }
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($c_id);
        return $customer_service_model->findByPk();
    }

    /**
     * 根据id查询退换货单信息
     * @param $c_ids
     * @return array|bool|null
     */
    public function findByPks($c_ids)
    {
        if(empty($c_ids)){
            return null;
        }
        $customer_service_model = new CustomerServiceModel();
        return $customer_service_model->findByPks($c_ids);
    }

    /**
     * 验证退换货单能否进行退换货
     * @param $service_ids
     * @param $action_type
     * @return array
     */
    public function validateCustomerStatusCanToAudit($service_ids, $action_type)
    {
        if (!in_array($action_type, ConstantConfig::confirmationActionType())){
            return [];
        }

        $service_info_arr = $this->findByPks($service_ids);
        if(empty($service_info_arr)){
            return [];
        }
        $y_array = [];
        $y_r_array = [];
        $y_e_array = [];
        $n_array = [];
        foreach($service_info_arr as $service){
            $service_status = $service['service_status'];
            $service_id = $service['id'];
            $order_id = $service['order_id'];
            if($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE &&
                $service_status == ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN){//待买家退回商品

                $y_array[] = $service_id;
                if($service["service_type"] == ConstantConfig::SERVICE_TYPE_RETURN){
                    $y_r_array[] = $service['order_id'];
                }else{
                    $y_e_array[] = $service['order_id'];
                }
            }elseif($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE &&
                $service_status == ConstantConfig::SERVICE_STATUS_WAITING_SELLER_AUDIT ){
                if((int)$service['can_cancel_examine'] == ConstantConfig::CANNOT_CANCEL_EXAMINE){
                    $n_array[] = ['sn'=>$service['order_sn'], 'msg'=>'物流已处理，不能进行反审'];
                    continue;
                }
                if((int)$service['apply_refund_process'] == ConstantConfig::APPLY_BY_CHANGE){
                    $n_array[] = ['sn'=>$service['order_sn'], 'msg'=>'退款信息正在处理，不能进行反审'];
                    continue;
                }

                $y_array[] = $service_id;

                if($service["service_type"] == ConstantConfig::SERVICE_TYPE_RETURN){
                    $y_r_array[] = $service['order_id'];
                }else{
                    $y_e_array[] = $service['order_id'];
                }
            }else{
                $n_array[] = ['sn'=>$service['order_sn'], 'msg'=>'服务单状态有误'];
            }
        }
        return ['y' => $y_array, 'n' => $n_array, 'y_rd' => $y_r_array, 'y_ed' => $y_e_array];
    }

    /**
     * 验证退换货单能否进行作废
     * @param $service_ids
     * @return array
     */
    public function validateServiceCancel($service_ids)
    {
        if(empty($service_ids)){
            return [];
        }
        $service_info_arr = $this->findByPks($service_ids);
        if(empty($service_info_arr)){
            return [];
        }

        $y_array = [];
        $n_array = [];
        $y_r_array = [];
        $y_e_array = [];

        foreach($service_info_arr as $service){
            $service_status = $service['service_status'];
            if($service_status == ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN){
                array_push($y_array, $service['id']);
                if($service["service_type"] == ConstantConfig::SERVICE_TYPE_RETURN){
                    $y_r_array[] = $service["order_id"];
                }else{
                    $y_e_array[] = $service["order_id"];
                }
            }else{
                array_push($n_array, $service['order_sn']);
            }
        }
        return ['y'=>$y_array, 'n'=>$n_array, 'y_rd' => $y_r_array, 'y_ed' => $y_e_array];
    }

    /**
     * 通过订单id, 和服务类型，查询订单申请服务信息
     * @param $order_id
     * @param $service_type
     * @return array|bool
     */
    public function findByOrderIdAndServiceType($order_id, $service_type){
        if (empty($order_id) or empty($service_type)){
            return [];
        }
        $customer_service_model = new CustomerServiceModel();
        $customer_service_res = $customer_service_model->findByOrderIdAndServiceType($order_id, $service_type);
        if(empty($customer_service_res)){
            return [];
        }
        $service_id = $customer_service_res['id'];
        $customer_service_detail_model = new CustomerServiceDetailsModel();
        $customer_service_detail_model->setServiceId($service_id);
        $detail_service = $customer_service_detail_model->findByServiceId();
        if (empty($detail_service)){
            return [];
        }
        $customer_service_res['details'] = $detail_service;

        return $customer_service_res;
    }


    /**
     * 服务单手机号是否可看
     * @param $customer_info
     * @return bool
     */
    public function isServiceMobileVisible($customer_info)
    {
        if (empty($customer_info)) {
            return false;
        }
        //客审前可看，客审后不可看
        if (intval($customer_info['service_status']) == ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN) {
            return true;
        }
        return false;
    }


    /**
     * 根据服务单id获取真实手机号
     * @param $service_id
     * @param $order_id
     * @return array
     */
    public function getRealMobile($service_id, $order_id)
    {
        if(empty($service_id) and empty($order_id)) {
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        //获取服务单信息
        $customer_service_model = new CustomerServiceModel();
        if (!empty($service_id)) {
            $customer_service_model->setId($service_id);
            $customer_info = $customer_service_model->findByPk();
        } else {
            $customer_info = $customer_service_model->findByOrderId($order_id);
        }
        if (empty($customer_info)) {
            return ['success' => false, 'msg' => '服务单信息获取失败'];
        }

        //校验能否查看手机号
        $is_mobile_visible = $this->isServiceMobileVisible($customer_info);
        if (!$is_mobile_visible) {
            return ['success' => false, 'msg' => '手机号不可看'];
        }
        return ['success' => true, 'mobile' => $customer_info['mobile'], 'telephone' => $customer_info['telephone']];
    }
}