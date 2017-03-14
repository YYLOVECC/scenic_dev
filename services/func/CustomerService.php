<?php
/**
 * 售后服务类 即退换货单
 * User: teresa
 * Date: 8/20/15
 * Time: 11:20 AM
 */
namespace app\services\func;

use app\components\SiteConfig;
use app\models\BaseMerchandiseInfoModel;
use app\models\BaseMerchandiseSpecificationsModel;
use app\models\MerchandiseInfoModel;
use app\models\OrderDetailsModel;
use app\models\OrderInfoModel;
use app\models\OrderPaymentDetailsModel;
use app\models\OrderRefundModel;
use app\security\OrderClient;
use app\services\logistics\CLogisticsService;
use app\services\order\CCustomerService;
use app\services\order\COrderService;
use app\services\region\CAreaService;
use app\services\region\CCityService;
use app\services\region\CProvinceService;
use app\services\store\CMerchandiseService;
use app\services\super\CLogService;
use app\util\CutWordsClient;
use app\util\GearmanClientUtils;
use app\util\RefundInfoProcess;
use app\util\StringUtil;
use Setting;
use Yii;

use app\util\ArrayUtil;
use app\util\ConstantConfig;
use app\models\CustomerServiceModel;
use app\models\CustomerServiceDetailsModel;
use yii\base\Exception;

class CustomerService
{
    /**
     * 保存退换货
     * @param $params
     * @param $order_info
     * @param $user_info
     * @return array
     */
    public function saveOrderChange($params, $order_info, $user_info)
    {
        if(empty($params) || empty($order_info)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }
        $order_id = $order_info['id'];
        $order_sn = $order_info['sn'];
        $refund_info = [];
        $order_refund_model = new OrderRefundModel();
        if ($params['is_refund'] == 1) {
            $refund_type = ArrayUtil::getVal($params, "refund_type", '');
            $refund_mode = ArrayUtil::getVal($params, "refund_mode", '');
            $refund_price = ArrayUtil::getVal($params, "refund_price", '');
            $refund_way = ArrayUtil::getVal($params, "union_name", '');
            $refund_account = ArrayUtil::getVal($params, "union_account", '');
            $account_name = ArrayUtil::getVal($params, "union_account_name", '');
            $refund_reason = ArrayUtil::getVal($params, "refund_reason");
            $refund_info = [
                'order_id' => $order_id,
                'order_sn' => $order_sn,
                'is_apply' => ConstantConfig::IS_APPLY_FALSE,
                'refund_type' => $refund_type,
                'refund_price' => $refund_price,
                'refunded_price' => '',
                'refund_mode' => $refund_mode,
                'refund_way' => $refund_way,
                'refund_account' => $refund_account,
                'account_name' => $account_name,
                'refund_reason' => $refund_reason,
                'refund_remark' => '',
                'refund_status' => ConstantConfig::REFUND_WAITING_FOR_AUDIT,
                'create_user_id' => $user_info['id'],
                'create_user_name' => $user_info['name'],
                'refund_examine_id' => '',
                'refund_examine_name' => '',
                'refund_at' => '',
                'created_at' => time(),
                'updated_at' => time()
            ];

        }else{
            $order_refund_info = $order_refund_model->getOrderRefundInfo($order_id);
            if (!empty($order_refund_info) and $order_refund_info['refund_status'] != ConstantConfig::CANCEL_REFUND) {
                $params['is_refund'] = 1;
                $params['refund_type'] = $order_refund_info['refund_type'];
                $params['refund_mode'] = $order_refund_info['refund_mode'];
                $params['refund_price'] = $order_refund_info['refund_price'];
                $params['service_price'] = $order_refund_info['refund_price'];
                $params['refund_reason'] = $order_refund_info['refund_reason'];
                $params['bank'] = $order_refund_info['bank'];
                $params['account_number'] = $order_refund_info['account_number'];
                $params['account_name'] = $order_refund_info['account_name'];
                $params['apply_refund_process'] = ConstantConfig::APPLY_BY_HAND;
            }
        }
        //获取退回物流公司信息
        $logistics_company_name = ArrayUtil::getVal($params, "logistics_company_name", '');
        $logistics_company_id = 0;
        if(!empty($logistics_company_name)){
            $c_logistics_service = new CLogisticsService();
            $logistics_info = $c_logistics_service->getLogisticsByName($logistics_company_name);
            if(!empty($logistics_info)){
                $logistics_company_id = $logistics_info["id"];
            }
        }

        $return_merchandises = ArrayUtil::getVal($params,'return_merchandises');
        //检测商品SKU信息是否正确
        $all_m_sn = [];
        foreach ($return_merchandises as $merchandise) {
            $m_sn = $merchandise["merchandise_sn"];
            $m_code = $merchandise["merchandise_specification_code"];

            if (empty($m_code)){
                $all_m_sn[] = $m_sn;
            }
        }
        if(!empty($all_m_sn)) {
            $base_merchandise_info_model =new BaseMerchandiseInfoModel();
            $all_m_sn_res = $base_merchandise_info_model->getMerchandiseBySnList($all_m_sn);
            if (empty($all_m_sn_res)){
                return ['success'=>false, 'msg'=>'获取商品基础资料信息失败'];
            }
            $m_dict = [];
            foreach ($all_m_sn_res as $item){
                $cur_m_sn = $item['merchandise_sn'];
                $cur_m_is_sku = $item['is_sku'];
                $m_dict[$cur_m_sn] = $cur_m_is_sku;
            }
            foreach ($all_m_sn as $m_sn) {
                if (!array_key_exists($m_sn, $m_dict)){
                    return ['success'=>false, 'msg'=>"商品：$m_sn 信息获取失败"];
                }
                if($m_dict[$m_sn]==ConstantConfig::IS_SKU_TRUE){
                    return ['success'=>false, 'msg'=>"商品：$m_sn 的SKU信息错误"];
                }
            }
        }

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $service_type =  ArrayUtil::getVal($params,'service_type', ConstantConfig::SERVICE_TYPE_RETURN);
            //生成退换货单号
            $status = ConstantConfig::STATUS_DEFAULT;
            $current_time = ArrayUtil::getVal($params,'created_at', Yii::$app->params['current_time']);
            //插入售后服务
            $customer_service_model = new CustomerServiceModel();
            $customer_service_model->setServiceType($service_type);
            $customer_service_model->setOrderId($order_id);
            $customer_service_model->setOrderSn($order_info['sn']);
            $customer_service_model->setOrderPrice($order_info['pay_price']);
            $customer_service_model->setOrderCourierNumber($order_info['courier_number']);
            $customer_service_model->setOrderLogisticsCompanyId($order_info['logistics_company_id']);
            $customer_service_model->setOrderLogisticsCompanyName($order_info['logistics_company_name']);
            $customer_service_model->setStoreId($order_info['store_id']);
            $customer_service_model->setStorePlatformType($order_info['store_platform_type']);
            $customer_service_model->setStoreName($order_info['store_name']);
            $customer_service_model->setConsignee($order_info['consignee']);
            $customer_service_model->setTelephone($order_info['telephone']);
            $customer_service_model->setMobile($order_info['mobile']);
            $customer_service_model->setProvinceId($order_info['province_id']);
            $customer_service_model->setProvinceName($order_info['province_name']);
            $customer_service_model->setCityId($order_info['city_id']);
            $customer_service_model->setCityName($order_info['city_name']);
            $customer_service_model->setDistrictId($order_info['district_id']);
            $customer_service_model->setDistrictName($order_info['district_name']);
            $customer_service_model->setAddress($order_info['address']);
            $customer_service_model->setServicePrice(ArrayUtil::getVal($params, 'service_price',0));
            $customer_service_model->setIsAll(ArrayUtil::getVal($params, 'is_all', 0));
            $customer_service_model->setServiceStatus(ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN);
            $customer_service_model->setLogisticsCompanyId($logistics_company_id);
            $customer_service_model->setLogisticsCompanyName($logistics_company_name);
            $customer_service_model->setCourierNumber(ArrayUtil::getVal($params, 'courier_number', ''));
            $customer_service_model->setRemark(ArrayUtil::getVal($params, 'remark', ''));
            $customer_service_model->setCanCancelExamine(ArrayUtil::getVal($params, 'can_cancel_examine',
                ConstantConfig::CAN_CANCEL_EXAMINE));

            //换货新单支付信息
            $customer_service_model->setPayType(ArrayUtil::getVal($params, 'pay_type', 0));
            $customer_service_model->setRemitBank(ArrayUtil::getVal($params, 'remit_bank', ''));
            $customer_service_model->setPayMode(ArrayUtil::getVal($params, 'pay_mode', 0));
            $customer_service_model->setPayStatus(ArrayUtil::getVal($params, 'pay_status', 0));

            $customer_service_model->setStatus($status);
            $customer_service_model->setCreatedAt($current_time);
            $customer_service_model->setUpdatedAt($current_time);
            //制单人信息
            $customer_service_model->setCreateUserId(ArrayUtil::getVal($user_info, 'id', 0));
            $customer_service_model->setCreateUserName(ArrayUtil::getVal($user_info, 'name', ''));

            //退款信息
            $customer_service_model->setIsRefund(ArrayUtil::getVal($params, 'is_refund', 0));//是否退款
            $customer_service_model->setRefundType(ArrayUtil::getVal($params, 'refund_type', 0));//退款类型
            $customer_service_model->setRefundMode(ArrayUtil::getVal($params, 'refund_mode', 0));//退款方式
            $customer_service_model->setBank(ArrayUtil::getVal($params, 'union_name', ''));//银行账户信息
            $customer_service_model->setAccountNumber(ArrayUtil::getVal($params, 'union_account',''));
            $customer_service_model->setAccountName(ArrayUtil::getVal($params, 'union_account_name',''));
            $customer_service_model->setRefundPrice(ArrayUtil::getVal($params, 'refund_price', 0));
            $customer_service_model->setRefundDistributionPrice(ArrayUtil::getVal($params, 'refund_distribution_price', 0));
            $customer_service_model->setRefundReason(ArrayUtil::getVal($params, 'refund_reason'));
            $customer_service_model->setApplyRefundProcess(ArrayUtil::getVal($params, 'apply_refund_process', ConstantConfig::APPLY_BY_CHANGE));

            //换货信息
            $customer_service_model->setMarginPrice(ArrayUtil::getVal($params, 'margin_price', 0));
            $customer_service_model->setBonus(ArrayUtil::getVal($params, 'bonus', 0));
            $customer_service_model->setShippingPrice(ArrayUtil::getVal($params, 'shipping_price', 0));
            $customer_service_model->setDistributionPrice(ArrayUtil::getVal($params, 'distribution_price', 0));

            //创建换货单
            $service_id = $customer_service_model->create();

            //插入售后服务详情
            $customer_service_details_model = new CustomerServiceDetailsModel();
            $command = $customer_service_details_model->createBatch();

            $customer_service_details_model->setServiceId($service_id);
            $customer_service_details_model->setServiceType($service_type);
            $customer_service_details_model->setOrderId($order_id);
            $customer_service_details_model->setOrderSn($order_info['sn']);
            $customer_service_details_model->setStatus($status);
            $customer_service_details_model->setCreatedAt($current_time);
            $customer_service_details_model->setUpdatedAt($current_time);
            //插入退入商品
            foreach ($return_merchandises as $merchandise) {
                if(array_key_exists("grouped_items", $merchandise)){
                    $relation_type = empty($merchandise["merchandise_specification_code"]) ?
                        ConstantConfig::GROUP_ITEMS_RELATION_SN : ConstantConfig::GROUP_ITEMS_RELATION_SPECIFICATION_CODE;
                    $relation_key= empty($merchandise["merchandise_specification_code"]) ?
                        $merchandise["merchandise_sn"] : $merchandise["merchandise_specification_code"];
                    foreach($merchandise["grouped_items"] as $gi){
                        $customer_service_details_model->setOrderDetailId(ArrayUtil::getVal($merchandise, 'detail_id', 0));
                        $customer_service_details_model->setReturnType(ConstantConfig::RETURN_TYPE_BACK);
                        $customer_service_details_model->setMerchandiseId(ArrayUtil::getVal($gi, 'merchandise_id', 0));
                        $customer_service_details_model->setMerchandiseSn(strtoupper($gi['merchandise_sn']));
                        $customer_service_details_model->setMerchandiseName($gi['merchandise_name']);
                        $customer_service_details_model->setMerchandiseSpecificationId(ArrayUtil::getVal($gi, 'merchandise_specification_id', 0));
                        $customer_service_details_model->setMerchandiseSpecificationCode($gi['merchandise_specification_code']);
                        $customer_service_details_model->setMerchandiseSpecificationName($gi['merchandise_specification_name']);
                        $customer_service_details_model->setMerchandiseType($merchandise['merchandise_type']);
                        $customer_service_details_model->setRelationType($relation_type);
                        $customer_service_details_model->setRelationKey($relation_key);
                        $customer_service_details_model->setNumbers($gi['numbers']);
                        $customer_service_details_model->setUnitPrice($gi['price']);
                        $customer_service_details_model->setTotalAmount(ArrayUtil::getVal($gi, "total_amount", 0.00));

                        $customer_service_details_model->createBatchExecute($command);
                    }
                }else{
                    $customer_service_details_model->setOrderDetailId(ArrayUtil::getVal($merchandise, 'detail_id', 0));
                    $customer_service_details_model->setReturnType(ConstantConfig::RETURN_TYPE_BACK);
                    $customer_service_details_model->setMerchandiseId(ArrayUtil::getVal($merchandise, 'merchandise_id', 0));
                    $customer_service_details_model->setMerchandiseSn(strtoupper($merchandise['merchandise_sn']));
                    $customer_service_details_model->setMerchandiseName($merchandise['merchandise_name']);
                    $customer_service_details_model->setMerchandiseSpecificationId(ArrayUtil::getVal($merchandise, 'merchandise_specification_id', 0));
                    $customer_service_details_model->setMerchandiseSpecificationCode($merchandise['merchandise_specification_code']);
                    $customer_service_details_model->setMerchandiseSpecificationName($merchandise['merchandise_specification_name']);
                    $customer_service_details_model->setMerchandiseType($merchandise['merchandise_type']);
                    $customer_service_details_model->setRelationType(0);
                    $customer_service_details_model->setRelationKey("");
                    $customer_service_details_model->setNumbers($merchandise['numbers']);
                    $customer_service_details_model->setUnitPrice($merchandise['unit_price']);
                    $customer_service_details_model->setTotalAmount(ArrayUtil::getVal($merchandise, "total_amount", 0.00));

                    $customer_service_details_model->createBatchExecute($command);
                }
            }
            if($service_type == ConstantConfig::SERVICE_TYPE_EXCHANGE){
                //插入换货商品
                $change_merchandises = ArrayUtil::getVal($params,'change_merchandises');
                foreach ($change_merchandises as $merchandise) {
                    $detail_id = (int)ArrayUtil::getVal($merchandise, "detail_id", 0);
                    if(array_key_exists("grouped_items", $merchandise)){
                        $relation_type = empty($merchandise["merchandise_specification_code"]) ?
                            ConstantConfig::GROUP_ITEMS_RELATION_SN : ConstantConfig::GROUP_ITEMS_RELATION_SPECIFICATION_CODE;
                        $relation_key= empty($merchandise["merchandise_specification_code"]) ?
                            $merchandise["merchandise_sn"] : $merchandise["merchandise_specification_code"];
                        foreach($merchandise["grouped_items"] as $gi){
                            $customer_service_details_model->setOrderDetailId($detail_id);
                            $customer_service_details_model->setReturnType(ConstantConfig::RETURN_TYPE_SWAPPED);
                            $customer_service_details_model->setMerchandiseId($gi['merchandise_id']);
                            $customer_service_details_model->setMerchandiseSn(strtoupper($gi['merchandise_sn']));
                            $customer_service_details_model->setMerchandiseName($gi['merchandise_name']);
                            $customer_service_details_model->setMerchandiseSpecificationId($gi['merchandise_specification_id']);
                            $customer_service_details_model->setMerchandiseSpecificationCode($gi['merchandise_specification_code']);
                            $customer_service_details_model->setMerchandiseSpecificationName($gi['merchandise_specification_name']);
                            $customer_service_details_model->setMerchandiseType($merchandise['merchandise_type']);
                            $customer_service_details_model->setRelationType($relation_type);
                            $customer_service_details_model->setRelationKey($relation_key);
                            $customer_service_details_model->setNumbers($gi['numbers']);
                            $customer_service_details_model->setUnitPrice($gi['unit_price']);
                            $customer_service_details_model->setTotalAmount(ArrayUtil::getVal($gi, "total_amount", 0.00));

                            $customer_service_details_model->createBatchExecute($command);
                        }
                    }else{
                        $customer_service_details_model->setOrderDetailId($detail_id);
                        $customer_service_details_model->setReturnType(ConstantConfig::RETURN_TYPE_SWAPPED);
                        $customer_service_details_model->setMerchandiseId($merchandise['merchandise_id']);
                        $customer_service_details_model->setMerchandiseSn(strtoupper($merchandise['merchandise_sn']));
                        $customer_service_details_model->setMerchandiseName($merchandise['merchandise_name']);
                        $customer_service_details_model->setMerchandiseSpecificationId($merchandise['merchandise_specification_id']);
                        $customer_service_details_model->setMerchandiseSpecificationCode($merchandise['merchandise_specification_code']);
                        $customer_service_details_model->setMerchandiseSpecificationName($merchandise['merchandise_specification_name']);
                        $customer_service_details_model->setMerchandiseType($merchandise['merchandise_type']);
                        $customer_service_details_model->setRelationType(0);
                        $customer_service_details_model->setRelationKey("");
                        $customer_service_details_model->setNumbers($merchandise['numbers']);
                        $customer_service_details_model->setUnitPrice($merchandise['unit_price']);
                        $customer_service_details_model->setTotalAmount(ArrayUtil::getVal($merchandise, "total_amount", 0.00));

                        $customer_service_details_model->createBatchExecute($command);
                    }
                }
            }
            //修改原订单退换货标识
            $order_info_model = new OrderInfoModel();
            $order_info_model->setUpdatedAt($current_time);
            $order_info_model->setId($order_id);
            $is_refund = ConstantConfig::RETURN_FALSE;
            $is_exchange = ConstantConfig::EXCHANGE_FALSE;
            if($service_type == ConstantConfig::SERVICE_TYPE_RETURN){
                $is_refund = ConstantConfig::RETURN_TRUE;
                $order_info_model->setIsRefund(ConstantConfig::RETURN_TRUE);
                $order_info_model->updateIsRefund();
            }else{
                $is_exchange = ConstantConfig::EXCHANGE_TRUE;
                $order_info_model->setIsExchange(ConstantConfig::EXCHANGE_TRUE);
                $order_info_model->updateIsExchange();
            }
            if (!empty($refund_info)){
                //生成退款单
                $add_res = $order_refund_model->saveRefundInfo($refund_info);
                if (!$add_res) {
                    throw new Exception('退款信息保存失败');
                }
                //更新订单退款状态
                $update_res = $order_info_model->updateRefundStatus($order_id,ConstantConfig::REFUND_WAITING_FOR_AUDIT);
                if(!$update_res) {
                    throw new Exception('订单退款状态更新失败');
                }
            }
            //订单操作记录
            $c_order_service = new COrderService();
            $action_res = $c_order_service->orderActionLogs([$order_info['id']], $user_info['id'], $user_info['name']);
            if(!$action_res){
                throw new Exception('订单操作记录日志错误');
            }

            //同步退换货标识至进销存系统
            $gearman_client_utils = new GearmanClientUtils();
            $result = $gearman_client_utils->syncOrderService(['orders'=>[['order_id' => $order_id, 'is_refund' => $is_refund, 'is_exchange' => $is_exchange]]]);
            if (!$result['success']) {
                throw new Exception($result['msg']);
            }
            $result = ['success'=>true, 'msg'=>'操作成功'];
            $transaction->commit();

            //同步订单信息至PPC
            $gearman_client_utils = new GearmanClientUtils();
            $orders = $c_order_service->getOrderInfoWithDetails($order_id);
            $gearman_client_utils->syncOrderInfo2PPC($orders);

        }catch (Exception $e){
            $transaction->rollBack();
            $result = ['success'=>false, 'msg'=>'退换货申请提交失败：'.$e->getMessage()];
        }
        return $result;
    }


    public function searchServiceList($params, $ordinal_str, $ordinal_type, $limit=0, $limit_size=20){
        //暂时不使用sphinx
//        if (!array_key_exists('consignee', $params)){
//            $consignee = '';
//        }else{
//            $consignee = $params['consignee'];
//        }
        //db查询
        $customer_service_model = new CustomerServiceModel();
        $count = $customer_service_model->countServiceList($params);
        $service_list = $customer_service_model->searchServiceList($params, $ordinal_str, $ordinal_type, $limit, $limit_size);
        //格式化退换货单数据
        $service_list = $this->_formatServiceList($service_list, $ordinal_str='', $ordinal_type='');
        return ['success'=>true, 'count'=>$count, 'data'=>$service_list];
//        if (empty($consignee)){
//            //db查询
//            $customer_service_model = new CustomerServiceModel();
//            $count = $customer_service_model->countServiceList($params);
//            $service_list = $customer_service_model->searchServiceList($params, $ordinal_str, $ordinal_type, $limit, $limit_size);
//            //格式化退换货单数据
//            $service_list = $this->_formatServiceList($service_list, $ordinal_str='', $ordinal_type='');
//            return ['success'=>true, 'count'=>$count, 'data'=>$service_list];
//        }else{
//            //sphinx查询
//            $res = $this->searchServiceFromSphinx($consignee, $params);
//            $count = $res['count'];
//            $ids = $res['destination'];
//            $service_list = $this->getServiceInfoList($ids);
//            $service_list = $this->_formatServiceList($service_list, $ordinal_str='', $ordinal_type='');
//            return ['success'=>true, 'count'=>$count, 'data'=>$service_list];
//        }
    }


    /**
     * 通过sphinx搜索订单信息
     * @param $key_words
     * @param $params
     * @return array
     */
    public function searchServiceFromSphinx($key_words, $params)
    {
        //TODO 相关ip配置，都需要从系统配置中活取
        //分词
        $config = Setting::getCutWordsClientConfig();
        $cut_client = new CutWordsClient($config['host'], $config['port']);
        $search_test_arr = $cut_client->cut_words($key_words);
        $search_keywords = implode('|', $search_test_arr);

        //搜索
        $shpinx_config = Setting::getSphinxClientConfig();
        $sc = new \SphinxClient();
        $sc->setServer($shpinx_config['host'], $shpinx_config['port']);
        $sc->setMatchMode(SPH_MATCH_EXTENDED2);

        // 设置搜索过滤条件
        $this->setParamsFilter($sc, $params);

        // 设置搜索字段权重
        $sc->setFieldWeights(array(
            'consignee' => 1000,
        ));

        //设置返回数
        $sc->SetLimits(0, 200, 200);
        $sc->setArrayResult(TRUE);
        $search_index = 'customer_service';
        $search_res = $sc->Query($search_keywords, $search_index);
        $count = count($search_res['matches']);
        //组装结果集id
        $destination = array();
        if ($search_res['matches']) {
            foreach ($search_res['matches'] as $val) {
                $destination[] = $val['id'];
            }
        }
        return array('count' => $count, 'destination' => $destination);
    }

    /**
     * 根据id批量获取退换货单信息
     * @param $ids
     * @return array|bool
     */
    public function getServiceInfoList($ids)
    {
        if(empty($ids)){
            return [];
        }
        $customer_service_model = new CustomerServiceModel();
        return $customer_service_model->findByPks($ids);
    }


    /**
     * 格式化退换货单列表数据
     * @param $service_data
     * @param $ordinal_str
     * @param $ordinal_type
     * @return mixed
     */
    private function _formatServiceList($service_data, $ordinal_str='', $ordinal_type='')
    {
        if(empty($service_data))
        {
            return $service_data;
        }
        $is_sort = false;
        if(!empty($ordinal_str) && !empty($ordinal_type)){
            $is_sort = true;
        }

        $service_status_arr = ConstantConfig::allServiceStatus();
        $service_type_arr = ConstantConfig::serviceTypeArray();
        $platform_type_arr = ConstantConfig::platformType();
        $fieldArr = array();
        $c_customer_service = new CCustomerService();
        foreach($service_data as $key => &$value) {
            // 增加地址的截取字符串
            if ($value['address']) {
                $service_data[$key]['short_address'] = mb_substr($value['address'], 0, 10, 'UTF-8');
            } else {
                $service_data[$key]['short_daddress'] = "";
            }
            $service_status = intval($value['service_status']);
            $value['store_platform_name'] = $platform_type_arr[$value['store_platform_type']];
            $value['service_status_str'] = $service_status_arr[$service_status];
            $value['service_type_str'] = $service_type_arr[$value['service_type']];
            $value['created_at_str'] = $value['created_at'] ? date('Y/m/d H:i:s', intval($value['created_at'])) : '';

            //手机号加密及可见标识
            $value['mobile'] = StringUtil::formatTelephone($value['mobile']);
            $value['telephone'] = StringUtil::formatTelephone($value['telephone']);
            $value['is_mobile_visible'] = $c_customer_service->isServiceMobileVisible($value);

            if($is_sort){
                $fieldArr[$key] = $value[$ordinal_str];
            }
        }
        if ($is_sort) {
            $sort = $ordinal_type == 'ASC' ? SORT_ASC : SORT_DESC;
            array_multisort($fieldArr, $sort, $service_data);
        }
        return $service_data;
    }

    /**
     * 修改退换货单的属性项：支持 'consignee', 'mobile'
     * @param $id
     * @param $field
     * @param $val
     * @param $action_id
     * @param $action_name
     * @return array
     */
    public function updateFieldById($id, $field, $val, $action_id=0, $action_name="")
    {
        if (empty($id) || empty($field)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }

        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ["success" => false, "msg" => "退换货单信息获取失败"];
        }

        //检测状态
        if($field != "remark" and (int)$service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN){
            return ['success'=>false, 'msg'=>'该订单不能编辑'];
        }
        $can_edit_field = $customer_service_model->getEditFieldArray();
        if (!array_key_exists($field, $can_edit_field)){
            return ['success'=>false, 'msg'=>'参数修改不合法'];
        }
        $customer_service_model->updateFieldById($id, $field, $val);

        //保存操作日志
        $c_log_service = new CLogService();
        $service_str = $service_info["service_type"] == ConstantConfig::SERVICE_TYPE_EXCHANGE ? "换货单" : "退货单";
        $content = "";
        if($field == "remark"){
            $content .= "编辑 " . $service_str. " ". $can_edit_field[$field];
        }
        $content .= " " . $service_info[$field] . "为" . $val;
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id, $action_name, $content,
            $field, $service_info[$field], $val);

        return ['success'=>true, 'msg'=>'修改成功'];
    }

    /**
     * 修改服务单退回物流单号
     * @param $id
     * @param $courier_number
     * @param int $action_id
     * @param string $action_name
     * @return array
     * @throws Exception
     */
    public function updateCourierNumberById($id, $courier_number, $action_id=0, $action_name="")
    {
        if (empty($id)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }

        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ["success" => false, "msg" => "退换货单信息获取失败"];
        }

        //检测状态
        if((int)$service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN and
            (int)$service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_SELLER_AUDIT){
            return ['success'=>false, 'msg'=>'该订单不能编辑'];
        }
        $customer_service_model->updateFieldById($id, "courier_number", $courier_number);

        //保存操作日志
        $c_log_service = new CLogService();
        $content = "编辑 退回物流单号 " . $service_info["courier_number"] . "为" . $courier_number;
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info['order_id'], $action_id, $action_name, $content,
            "courier_number", $service_info["courier_number"], $courier_number);

        return ['success'=>true, 'msg'=>'修改成功'];
    }

    /**
     * 修改退回物流公司
     * @param $id
     * @param $new_logistics_company_name
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateLogisticsCompanyById($id, $new_logistics_company_name, $action_id=0, $action_name="")
    {
        if (empty($id)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }

        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ["success" => false, "msg" => "退换货单信息获取失败"];
        }

        //检测状态
        if((int)$service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN and
            (int)$service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_SELLER_AUDIT){
            return ['success'=>false, 'msg'=>'该订单不能编辑'];
        }
        $new_logistics_company_id = 0;
        if(!empty($new_logistics_company_name)){
            //获取物流公司信息
            $c_logistics_service = new CLogisticsService();
            $logistics_info = $c_logistics_service->getLogisticsByName($new_logistics_company_name);
            if(empty($logistics_info)){
                $new_logistics_company_id = $logistics_info["id"];
            }
        }

        $customer_service_model->setLogisticsCompanyId($new_logistics_company_id);
        $customer_service_model->setLogisticsCompanyName($new_logistics_company_name);
        $customer_service_model->updateLogisticsCompany();

        //保存操作日志
        $c_log_service = new CLogService();
        $content = "编辑 退回物流公司 \"" . $service_info["logistics_company_name"] . "\"为\"" . $new_logistics_company_name . "\"";
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id, $action_name, $content,
            "logistics_company_name", $service_info["logistics_company_name"], $new_logistics_company_name);

//        //拼装数据，同步数据到psi
//        $customer_service_details_model = new CustomerServiceDetailsModel();
//        $customer_service_details_model->setServiceId($id);
//        $customer_service_detail_list = $customer_service_details_model->findByServiceId();
//
//        $service_info['details'] = $customer_service_detail_list;
//        $sync_data = ['services'=>[$service_info]];
//        $order_client = new OrderClient();
//        $res = $order_client->syncCustomerServiceData($sync_data);
//        if(!$res['success']){ //如果失败，返回原因
//            return $res;
//        }
        return ['success'=>true, 'msg'=>'修改成功'];
    }

    /**
     * 修改退货单退款方式
     * @param $id
     * @param $refund_mode
     * @param string $bank
     * @param string $account_name
     * @param string $account_number
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateServiceRefundMode($id, $refund_mode, $bank='', $account_name='', $account_number='', $action_id=0, $action_name="")
    {
        if (empty($id) || empty($refund_mode)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }
        if(intval($refund_mode) == ConstantConfig::REFUND_OTHER_WAY){
            if(empty($bank) || empty($account_name) || empty($account_number)){
                return ['success'=>false, 'msg'=>'退入用户帐号信息不能为空'];
            }
        }
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($id);
        #获取退货单信息
        $service_info = $customer_service_model->findByPk();
        if($service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN ||
            $service_info['is_refund'] == ConstantConfig::REFUND_FALSE ||
            $service_info['service_type']!=ConstantConfig::SERVICE_TYPE_RETURN){
            return ['success'=>false, 'msg'=>'不能修改该单的退款方式'];
        }
        $customer_service_model->setRefundMode($refund_mode);
        $customer_service_model->setBank($bank);
        $customer_service_model->setAccountName($account_name);
        $customer_service_model->setAccountNumber($account_number);
        $result = $customer_service_model->updateRefundMode();
        //修改退货单信息
        $order_id = $service_info['order_id'];
        $order_refund_model = new OrderRefundModel();
        $order_refund_info = $order_refund_model->getOrderRefundInfo($order_id);
        if (empty($order_refund_info) or $order_refund_info['refund_status'] == ConstantConfig::CANCEL_REFUND) {
            return ["success" => false, "msg" => "退款单信息获取失败"];
        }
        $order_refund_model ->setRefundMode($refund_mode);
        $order_refund_model ->setRefundWay($bank);
        $order_refund_model ->setRefundAccount($account_number);
        $order_refund_model ->setAccountName($account_name);
        $order_refund_model ->setId($order_refund_info['id']);
        $refund_result = $order_refund_model->updateRefundMode();
        if(!$refund_result){
            throw new Exception('更新退款单退款方式失败');
        }

        if ($result) {
        //保存操作日志
            $c_log_service = new CLogService();
            $refund_mode_arr = ConstantConfig::refundTypeArray();
            $old_value = ArrayUtil::getVal($refund_mode_arr, $service_info["refund_mode"], $service_info["refund_mode"]);
            $new_value = ArrayUtil::getVal($refund_mode_arr, $refund_mode, $refund_mode);
            //
            $content = "编辑 退货退款方式" . $old_value . "为" . $new_value;
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id, $action_name, $content,
                "refund_mode", $service_info["refund_mode"], $refund_mode);
            return ['success' => true, 'msg' => '修改成功'];
        } else {
            return ['success' => false, 'msg' => '修改失败'];
        }
    }

    /**
     * 修改退货单退货金额、换货单代收货款
     * @param $service_price
     * @param $service_info
     * @param $action_id
     * @param $action_name
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function updateServicePrice($service_price, $service_info, $action_id=0, $action_name="")
    {
        if(!isset($service_price) || empty($service_info)){
            return ['success'=>false, 'msg'=>'参数传递错误'];
        }
        $service_id = $service_info['id'];
        $service_order_id = $service_info['order_id'];

        $service_type = $service_info['service_type'];
        $current_time = time();
        if($service_type==ConstantConfig::SERVICE_TYPE_RETURN){//修改退款金额
            //获取退换货单详情
            $customer_service_detail_model = new CustomerServiceDetailsModel();
            $customer_service_detail_model->setServiceId($service_id);
            $service_details = $customer_service_detail_model->findByServiceId();
            if(empty($service_details)){
                return ['success'=>false, 'msg'=>'退换货单信息有误'];
            }
            $return_merchandises = [];//退入商品
            foreach($service_details as $detail){
                if($detail['return_type'] == ConstantConfig::RETURN_TYPE_BACK){//退入
                    array_push($return_merchandises, $detail);
                }
            }

            $c_order_service = new COrderService();
            $order_info = $c_order_service->getOrderInfo($service_info['order_id']);

            //计算退入商品金额
            $return_merchandise_price = 0;
            foreach($return_merchandises as $r_merchandise){
                $return_merchandise_price += floatval($r_merchandise['total_amount']);
            }

            //计算退款金额 退款金额=退款商品总额*（（应付金额+积分支付金额）/订单总额）
            $return_price = floatval($order_info['original_price']) == 0 ? 0: floatval(sprintf('%.2f',
                $return_merchandise_price*floatval($order_info['pay_price'])/floatval($order_info['original_price'])));

            $calculate_refund_price = $return_price + floatval($service_info['refund_distribution_price']);
            $max_margin_refund_price = floatval(SiteConfig::get('max_margin_refund_price'));
            if($service_price>($calculate_refund_price+$max_margin_refund_price)){
                return ['success'=>false, 'msg'=>'退款金额不能比系统计算退款金额'.$calculate_refund_price.'多过'.$max_margin_refund_price.'!'];
            }

            //修改退款金额
            $customer_service_model = new CustomerServiceModel();
            $customer_service_model->setId($service_id);
            $customer_service_model->setServicePrice($service_price);
            $customer_service_model->setRefundPrice($service_price);
            $customer_service_model->setUpdatedAt($current_time);
            $customer_service_model->updateReturnPrice();
            //修改退款单的退款金额
            $order_refund_model = new OrderRefundModel();
            $order_refund_info = $order_refund_model->getOrderRefundInfo($service_order_id);
            if (empty($order_refund_info) or $order_refund_info['refund_status'] == ConstantConfig::CANCEL_REFUND) {
                return ["success" => false, "msg" => "退款单信息获取失败"];
            }
            $order_refund_id = $order_refund_info['id'];
            $order_refund_model->setId($order_refund_id);
            $order_refund_model->setRefundPrice($service_price);
            $order_refund_model->setUpdatedAt($current_time);
            $order_refund_model->updateRefundPrice();

            $content = "编辑 退货退款金额 " . $service_info["service_price"] . "为" . $service_price;
            $result = ['success'=>true, 'msg'=>'退款金额修改成功'];
        }else{//修改代收货款
            //计算代收货款
            $calculate_service_price = floatval($service_info['margin_price']) +
                floatval($service_info['shipping_price']) + floatval($service_info['distribution_price']);
//            if($service_price>$calculate_service_price){
//                return ['success'=>false, 'msg'=>'代收货款不能大于系统计算代收货款'.$calculate_service_price.'！'];
//            }
            //计算让利金额 系统计算代收货款-实际代收货款
            $bonus = $calculate_service_price - $service_price;
//            $max_bonus_point = floatval(SiteConfig::get('max_bonus_point'));
//            if($bonus>$max_bonus_point*$calculate_service_price){
//                return ['success'=>false, 'msg'=>'让利金额最大值不能超过系统计算代收货款的'. (100*$max_bonus_point) .'%!'];
//            }

            //修改代收货款及让利金额
            $customer_service_model = new CustomerServiceModel();
            $customer_service_model->setId($service_id);
            $customer_service_model->setServicePrice($service_price);
            $customer_service_model->setBonus($bonus);
            $customer_service_model->setUpdatedAt($current_time);
            $customer_service_model->updateCollectPrice();

            $need_update_pay_status = false;
            $pay_status = 0;
            //当代收货款>0时 货到付款已付款需变更为未付款
            if(floatval($service_price) > 0 and $service_info['pay_type'] == ConstantConfig::PAY_TYPE_OFFLINE
                and $service_info['pay_status'] != ConstantConfig::PAY_STATUS_UNPAID){
                $need_update_pay_status = true;
                $pay_status = ConstantConfig::PAY_STATUS_UNPAID;
            }
            //当代收货款等于0时 货到付款未付款需变更为未付款
            if(floatval($service_price) == 0 and $service_info['pay_type'] == ConstantConfig::PAY_TYPE_OFFLINE
                and $service_info['pay_status'] != ConstantConfig::PAY_STATUS_PAID){
                $need_update_pay_status = true;
                $pay_status = ConstantConfig::PAY_STATUS_PAID;
            }
            //修改付款状态
            if($need_update_pay_status){
                $customer_service_model->setId($service_id);
                $customer_service_model->setPayStatus($pay_status);
                $customer_service_model->setUpdatedAt($current_time);
                $customer_service_model->updatePayStatus();
            }


            $content = "编辑 换货代收货款 " . $service_info["service_price"] . "为" . $service_price;
            $result = ['success'=>true, 'msg'=>'代收货款修改成功', 'bonus'=>$bonus];
        }
        //操作日志记录
        $c_log_service = new CLogService();
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
            $action_name, $content, "service_price", $service_info["service_price"], $service_price);

        return $result;
    }

    /**
     * 编辑退换货物流
     * @param $service_id
     * @param $shipping_price
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateServiceShippingPrice($service_id, $shipping_price, $action_id=0, $action_name=""){
        //获取退换货单信息
        $c_customer_service = new CCustomerService();
        $service_info = $c_customer_service->findByPk($service_id);
        if(empty($service_info)){
            return ['success'=>false, 'msg'=>'退换货单不存在或已被删除'];
        }
        //物流费用改变后,只会影响代收汇款
        //代收货款 = 支付差额 + 物流费用 + 分销价 - 让利
        $calculate_service_price = floatval($service_info['margin_price']) + floatval($shipping_price) + floatval($service_info['distribution_price']) - floatval($service_info["bonus"]);
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $customer_service_model->setServicePrice($calculate_service_price);
        $customer_service_model->setShippingPrice($shipping_price);
        $customer_service_model->setUpdatedAt(time());
        try{
            $customer_service_model->updateShippingPrice();

            //操作日志
            $c_log_service = new CLogService();
            $type_str = ConstantConfig::serviceTypeArray()[(int)$service_info["service_type"]];
            $content = "编辑 " . $type_str . "物流费用 " . $service_info["shipping_price"] . "为" . $shipping_price;
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
                $action_name, $content, "shipping_price", $service_info["shipping_price"], $shipping_price);
        }catch (Exception $e){
            return ['success'=>false, 'msg'=>'系统错误:',$e->getMessage()];
        }
        return ['success'=>true, 'msg'=>'操作成功'];
    }


    /**
     * 编辑退换货分销价
     * @param $service_id
     * @param $distribution_price
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateServiceDistributionPrice($service_id, $distribution_price, $action_id=0, $action_name=""){
        //获取退换货单信息
        $c_customer_service = new CCustomerService();
        $service_info = $c_customer_service->findByPk($service_id);
        if(empty($service_info)){
            return ['success'=>false, 'msg'=>'退换货单不存在或已被删除'];
        }
        if(floatval($distribution_price) < 0){
            return ['success'=>false, 'msg'=>'分销价不能为负'];
        }
        //分销价改变后,只会影响代收汇款
        //代收货款 = 支付差额 + 物流费用 + 分销价 - 让利
        $calculate_service_price = floatval($service_info['margin_price']) + floatval($service_info['shipping_price']) + floatval($distribution_price) - floatval($service_info["bonus"]);
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $customer_service_model->setServicePrice($calculate_service_price);
        $customer_service_model->setDistributionPrice($distribution_price);
        $customer_service_model->setUpdatedAt(time());
        try{
            $customer_service_model->updateDistributionPrice();

            //操作日志
            $c_log_service = new CLogService();
            $type_str = ConstantConfig::serviceTypeArray()[(int)$service_info["service_type"]];
            $content = "编辑 " . $type_str . "分销价 " . $service_info["distribution_price"] . "为" . $distribution_price;
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
                $action_name, $content, "distribution_price", $service_info["distribution_price"], $distribution_price);
        }catch (Exception $e){
            return ['success'=>false, 'msg'=>'系统错误:',$e->getMessage()];
        }
        return ['success'=>true, 'msg'=>'操作成功'];
    }


    /**
     * 编辑退换货退款金额
     * @param $service_id
     * @param $refund_price
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateServiceRefundPrice($service_id, $refund_price, $action_id=0, $action_name="")
    {
        if (empty($service_id)) {
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        $c_customer_service = new CCustomerService();
        $service_info = $c_customer_service->findByPk($service_id);
        $order_id = $service_info['order_id'];
        if (empty($service_info)) {
            return ['success' => false, 'msg' => '退换货单不存在或已被删除'];
        }
        if ((int)$service_info['is_refund'] == ConstantConfig::REFUND_FALSE) {
            return ['success' => false, 'msg' => '退换货单无需退款'];
        }

        //获取退换货单详情
        $customer_service_detail_model = new CustomerServiceDetailsModel();
        $customer_service_detail_model->setServiceId($service_id);
        $service_details = $customer_service_detail_model->findByServiceId();
        if (empty($service_details)) {
            return ['success' => false, 'msg' => '退换货单信息有误'];
        }
        $return_merchandises = [];//退入商品
        $swap_out_merchandises = [];//换出商品
        foreach ($service_details as $detail) {
            if ($detail['return_type'] == ConstantConfig::RETURN_TYPE_BACK) {//退入
                array_push($return_merchandises, $detail);
            }else{
                array_push($swap_out_merchandises, $detail);
            }
        }
        //计算退入商品金额
        $return_merchandise_price = 0;
        foreach ($return_merchandises as $r_merchandise) {
            $return_merchandise_price += floatval($r_merchandise['total_amount']);
        }

        $service_type = (int)$service_info['service_type'];
        if ($service_type == ConstantConfig::SERVICE_TYPE_RETURN) {//修改退款金额


            $c_order_service = new COrderService();
            $order_info = $c_order_service->getOrderInfo($service_info['order_id']);

            //计算退款金额 退款金额=退款商品总额*（（应付金额+积分支付金额）/订单总额）
            $return_price = floatval(sprintf('%.2f',
                $return_merchandise_price * floatval($order_info['pay_price']) / floatval($order_info['original_price'])));

            $calculate_refund_price = $return_price + floatval($service_info['refund_distribution_price']);
            $max_margin_refund_price = floatval(SiteConfig::get('max_margin_refund_price'));
            if ($refund_price > ($calculate_refund_price + $max_margin_refund_price)) {
                return ['success' => false, 'msg' => '退款金额比大于系统计算退款金额' . $calculate_refund_price . '多过'.$max_margin_refund_price.'!'];
            }
        } else {
            //计算换出商品金额
            $swap_out_merchandise_price = 0;
            foreach ($swap_out_merchandises as $s_merchandise) {
                $swap_out_merchandise_price += floatval($s_merchandise['total_amount']);
            }

            //计算换出差额
            $margin_price = floatval($swap_out_merchandise_price) - floatval($return_merchandise_price);
            if($margin_price >= 0){
                return ['success' => false, 'msg' => '退换货单无需退款'];
            }
            $calculate_refund_price = abs($margin_price) + floatval($service_info['refund_distribution_price']);
            if ($refund_price > $calculate_refund_price) {
                return ['success' => false, 'msg' => '退款金额不能大于系统计算退款' . $calculate_refund_price . '！'];
            }
        }
        //修改退款金额
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $customer_service_model->setServicePrice($refund_price);
        $customer_service_model->setRefundPrice($refund_price);
        $customer_service_model->setUpdatedAt(Yii::$app->params['current_time']);
        $customer_service_model->updateReturnPrice();
        //修改退款单的退款金额
        $order_refund_model = new OrderRefundModel();
        $order_refund_info = $order_refund_model->getOrderRefundInfo($order_id);
        $order_refund_id = $order_refund_info['id'];
        $order_refund_model->setId($order_refund_id);
        $order_refund_model->setRefundPrice($refund_price);
        $order_refund_model->setUpdatedAt(time());
        $order_refund_model->updateRefundPrice($order_refund_id);

        $type_str = ConstantConfig::serviceTypeArray()[(int)$service_info["service_type"]];
        $content = "编辑 ". $type_str ."退款金额 " . $service_info["service_price"] . "为" . $refund_price;
        $result = ['success' => true, 'msg' => '退款金额修改成功'];
        //操作日志记录
        $c_log_service = new CLogService();
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
            $action_name, $content, "refund_price", $service_info["refund_price"], $refund_price);
        return $result;
    }



    /**
     * 编辑退换货分销退款
     * @param $service_id
     * @param $refund_distribution_price
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateServiceRefundDistribution($service_id, $refund_distribution_price, $action_id=0, $action_name=""){
        if (empty($service_id)) {
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        //获取退换货单信息
        $c_customer_service = new CCustomerService();
        $service_info = $c_customer_service->findByPk($service_id);
        $order_id = $service_info['order_id'];
        $service_type = $service_info['service_type'];
        if(empty($service_info)){
            return ['success'=>false, 'msg'=>'退换货单不存在或已被删除'];
        }
        if(floatval($refund_distribution_price) < 0){
            return ['success'=>false, 'msg'=>'分销退款金额不能为负'];
        }
        //分销退款金额改变后,只会影响退款金额
        $new_refund_price = floatval($service_info['refund_price']) - floatval($service_info['refund_distribution_price']) + floatval($refund_distribution_price);
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        if((int)$service_info['service_type'] == ConstantConfig::SERVICE_TYPE_RETURN){
            //退货需改变service_price
            $customer_service_model->setServicePrice($new_refund_price);
        }
        $customer_service_model->setRefundPrice($new_refund_price);
        $customer_service_model->setRefundDistributionPrice($refund_distribution_price);
        $customer_service_model->setUpdatedAt(time());
        //修改退款单的退款金额
        $order_refund_model = new OrderRefundModel();
        $order_refund_info = $order_refund_model->getOrderRefundInfo($order_id);
        if(empty($order_refund_info) or $order_refund_info['refund_status'] == ConstantConfig::CANCEL_REFUND) {
            return ['success' =>false, 'msg' =>'退款单信息获取失败'];
        }
        $order_refund_id = $order_refund_info['id'];
        $order_refund_model->setId($order_refund_id);
        $order_refund_model->setRefundPrice($new_refund_price);
        $order_refund_model->setUpdatedAt(time());
        try{
            $customer_service_model->updateRefundDistributionPrice();
            $order_refund_model -> updateRefundPrice();
            //操作日志
            $c_log_service = new CLogService();
            $type_str = ConstantConfig::serviceTypeArray()[(int)$service_info["service_type"]];
            $content = "编辑 " . $type_str . "分销退款金额 " . $service_info["refund_distribution_price"] . "为" . $refund_distribution_price;
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
                $action_name, $content, "refund_distribution_price", $service_info["refund_distribution_price"], $refund_distribution_price);
        }catch (Exception $e){
            return ['success'=>false, 'msg'=>'系统错误:',$e->getMessage()];
        }
        return ['success'=>true, 'msg'=>'操作成功', 'service_type'=>$service_type];
    }

    /**
     * 修改换货单地址信息
     * @param $service_id
     * @param $province_id
     * @param $city_id
     * @param $area_id
     * @param $address
     * @param int $action_id
     * @param string $action_name
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function editServiceRegion($service_id, $province_id, $city_id, $area_id, $address, $action_id=0, $action_name="")
    {
        if (!is_int($service_id) || !is_int($province_id) || !is_int($city_id) || !is_int($area_id)) {
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        if ($service_id <= 0 || $province_id <= 0 || $city_id <= 0 || $area_id <= 0 || empty($address)) {
            return ['success' => false, 'msg' => '参数传递不全'];
        }
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ["success" => false, "msg" => "换货信息获取失败"];
        }

        //得到省市区的对象信息，获取具体的名称
        $c_province_service = new CProvinceService();
        $province_info = $c_province_service->getProvinceInfo($province_id);
        $province_name = empty($province_info) ? '' : $province_info['value'];

        $c_city_service = new CCityService();
        $city_info = $c_city_service->getCityInfo($city_id);
        $city_name = empty($city_info) ? '' : $city_info['value'];

        $c_area_service = new CAreaService();
        $area_info = $c_area_service->getAreaInfo($area_id);
        $area_name = empty($area_info) ? '' : $area_info['value'];

        //修改换货单的位置信息
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $customer_service_model->setProvinceId($province_id);
        $customer_service_model->setProvinceName($province_name);
        $customer_service_model->setCityId($city_id);
        $customer_service_model->setCityName($city_name);
        $customer_service_model->setDistrictId($area_id);
        $customer_service_model->setDistrictName($area_name);
        $customer_service_model->setAddress($address);
        $customer_service_model->setUpdatedAt(Yii::$app->params['current_time']);
        $result = $customer_service_model->updateRegionInfo();
        if (!$result) {
            return ['success' => false, 'msg' => '修改失败'];
        }

        //保存操作日志
        $c_log_service = new CLogService();
        $content = "编辑 换货地址";
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
            $action_name, $content);
        return ['success' => true, 'msg' => '修改成功', 'province_id' => $province_id, 'province_name' => $province_name,
            'city_id' => $city_id, 'city_name' => $city_name, 'area_id' => $area_id, 'area_name' => $area_name, 'address' => $address];
    }


    /**
     * 客审/反审
     * @param $service_ids
     * @param $action_type
     * @param $audit_user_id
     * @param $audit_user_name
     * @return array
     * @throws \yii\db\Exception
     */
    public function toOrCancelAuditService($service_ids, $action_type, $audit_user_id, $audit_user_name)
    {
        //检查退换货单能否进行客审/反审
        $c_customer_service = new CCustomerService();
        $validate_for_service = $c_customer_service->validateCustomerStatusCanToAudit($service_ids, $action_type);
        if (empty($validate_for_service) || empty($validate_for_service['y'])) {
            //错误信息
            $error_str = '没有退换货单能够进行处理';
            if (isset($validate_for_service['n'])) {
                $n_orders = $validate_for_service['n'];
                if (!empty($n_orders)) {
                    $error_msg = [];
                    foreach ($n_orders as $v) {
                        if (!in_array($v['msg'], $error_msg)) {
                            $error_msg[$v['msg']] = [];
                        }
                        array_push($error_msg[$v['msg']], $v['sn']);
                    }
                    $error_str .= '，';
                    foreach ($error_msg as $msg => $sns) {
                        $error_str .= implode('、', $sns) . ':' . $msg;
                    }
                }
            }

            return ['success' => false, 'msg' => $error_str];

        }

        //获取客审/反审后目标状态
        $action_relation_service_status = ConstantConfig::confirmationRelationServiceStatus()[$action_type];
        $y_service_ids = $validate_for_service['y'];
        $n_services = $validate_for_service['n'];

        $customer_service_model = new CustomerServiceModel();
        $order_refund_model = new OrderRefundModel();
        $order_info_model = new OrderInfoModel();
        $order_pay_details_model = new OrderPaymentDetailsModel();
        $customer_service_info = $customer_service_model->findByPks($y_service_ids);
        $need_process_ids = [];//order_id
        foreach ($customer_service_info as $customer_detail) {
            $need_process_ids[] = $customer_detail['order_id'];
        }
        $order_refund_info_list = $order_refund_model->getOrderRefundInfoList($need_process_ids);
        $order_info_ids = []; //订单id
        $order_refund_ids = [];//退款单id
        foreach ($order_refund_info_list as $order_refund_item) {
            if ($order_refund_item['is_apply'] == ConstantConfig::IS_APPLY_FALSE) {
                $order_info_ids[] = $order_refund_item['order_id'];
                $order_refund_ids[] =$order_refund_item['id'];
            }
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            //反审和客审修改退款单的状态
            if (!empty($order_refund_ids)) {
                if ($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE) {
                    $order_refund_model->setRefundStatus(ConstantConfig::REFUND_STATUS_REFUNDING);
                    $order_refund_model->setRefundExamineId($audit_user_id);
                    $order_refund_model->setRefundExamineName($audit_user_name);
                    $order_refund_model->setRefundExamineAt(time());
                    $order_refund_model->setUpdatedAt(time());
                    $order_refund_model->updateRefundStatusByIds($order_refund_ids);
                } else {
                    $order_refund_model->updateRefundStatus($order_refund_ids, ConstantConfig::REFUND_STATUS_REFUNDING);
                    //修改订单表退款状态
                    $order_info_model->updateRefundStatus($order_info_ids, ConstantConfig::REFUND_STATUS_REFUNDING );
                }
            }
            //修改退换货单状态
            $customer_service_model->setServiceStatus($action_relation_service_status);
            $customer_service_model->setAuditUserId($audit_user_id);
            $customer_service_model->setAuditUserName($audit_user_name);
            $customer_service_model->setUpdatedAt(Yii::$app->params['current_time']);
            $res = $customer_service_model->updateServiceStatusByIds($y_service_ids);
            if (!$res) {
                throw new Exception('更改退换货单售后状态错误：客审失败');
            }
            $sync_res = $this->syncCustomerServiceData($y_service_ids);
            if(!$sync_res['success']){
                throw new Exception($sync_res['msg']);
            }

            $transaction->commit();
            //封装同步数据
            if (!empty($order_refund_ids)) {
                $need_order_refund_info = $order_refund_model->getOrderRefundInfoList($order_refund_ids);
                $order_info_dict = [];
                $order_pay_details_dict = [];
                $need_order_refund_data = [];//需要同步的数据
                $need_order_info_list = $order_info_model->getOrderInfoList($order_info_ids);
                $order_pay_details_model = new OrderPaymentDetailsModel();
                $need_order_pay_details = $order_pay_details_model->getOrderPaymentDetailsByOIds($order_info_ids);
                foreach ($need_order_info_list as $order_info) {
                    $id = $order_info['id'];
                    $order_info_dict[$id] = $order_info;
                }
                foreach ($need_order_pay_details as $order_pay_detail) {
                    $order_id = $order_pay_detail['order_id'];
                    $order_pay_details_dict[$order_id][] = $order_pay_detail;
                }
                foreach ($need_order_refund_info as $order_refund_item) {
                    $order_id = $order_refund_item['order_id'];
                    $need_order_info = $order_info_dict[$order_id];
                    $order_refund_item ['store_id'] = $need_order_info['store_id'];
                    $order_refund_item ['store_name'] = $need_order_info['store_name'];
                    $order_refund_item ['pay_price'] = $need_order_info['pay_price'];
                    $order_refund_item ['order_status'] = $need_order_info['order_status'];
                    $order_refund_item ['consignee'] = $need_order_info['consignee'];
                    $order_refund_pay_details = $order_pay_details_dict[$order_id];
                    $need_order_refund_data[] = ['order_refund_info' =>$order_refund_item, 'order_refund_pay_details' => $order_refund_pay_details];
                }
            }
            //返回售后状态有误的退换货单
            $error_str = '';
            if (!empty($n_services)) {
                $error_msg = [];
                foreach ($n_services as $v) {
                    if (!in_array($v['msg'], $error_msg)) {
                        $error_msg[$v['msg']] = [];
                    }
                    array_push($error_msg[$v['msg']], $v['sn']);
                }
                $error_str = '，下列订单无法操作 ';
                foreach ($error_msg as $msg => $sns) {
                    $error_str .= implode('、', $sns) . ':' . $msg;
                }
            }
            //同步退款数据
            if(!empty($need_order_refund_data)) {
                $refund_info_process = new RefundInfoProcess();
                $refund_info_process->syncRefundInfo($need_order_refund_data);
            }
            return ['success' => True, 'msg' => '操作成功' . $error_str, 'r_order_ids' => $validate_for_service["y_rd"],
                'e_order_ids' => $validate_for_service["y_ed"]];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '客审失败：' . $e->getMessage()];
        }
    }

    public function syncCustomerServiceData($service_ids){
        $customer_service_model = new CustomerServiceModel();
        $service_list = $customer_service_model->findByPks($service_ids);

        $customer_service_detail_model = new CustomerServiceDetailsModel();
        $service_details_list = $customer_service_detail_model->getCustomerServiceDetails($service_ids);
        if(empty($service_details_list)){
            return ['success'=>false, 'msg'=>'服务单明细获取失败'];
        }
        $all_code = [];
        $all_sn = [];
        foreach($service_details_list as $d_item){
            $d_sn = $d_item['merchandise_sn'];
            $d_code = ArrayUtil::getVal($d_item, 'merchandise_specification_code');
            if(!empty($d_code)){
                $all_code[] = $d_code;
            }else{
                $all_sn[] = $d_sn;
            }
        }

        $all_base_merchandise_dict = [];
        if(!empty($all_sn)){
            $base_merchandise_info_model = new BaseMerchandiseInfoModel();
            $all_base_merchandise_list = $base_merchandise_info_model->getMerchandiseBySnList(array_unique($all_sn));
            if(empty($all_base_merchandise_list)){
                foreach($all_base_merchandise_list as $b_m_item){
                    $all_base_merchandise_dict[$b_m_item['merchandise_sn']] = $b_m_item;
                }
            }
        }
        $all_base_specification_dict = [];
        if(!empty($all_code)){
            $base_merchandise_specification_model = new BaseMerchandiseSpecificationsModel();
            $base_specification_list = $base_merchandise_specification_model->getMerchandiseSpecificationsByCodeList(array_unique($all_code));
            if(!empty($base_specification_list)){
                foreach($base_specification_list as $b_s_item){
                    $all_base_specification_dict[$b_s_item['merchandise_specification_code']] = $b_s_item;
                }
            }
        }

        $service_details_dict = [];
        foreach($service_details_list as $detail){
            $c_service_id = $detail['service_id'];
            $d_sn = $detail['merchandise_sn'];
            $d_code = ArrayUtil::getVal($detail, 'merchandise_specification_code');
            if(!empty($d_code)){
                $detail['barcode'] = ArrayUtil::getVal($all_base_specification_dict[$d_code], 'barcode');
            }else{
                $detail['barcode'] = ArrayUtil::getVal($all_base_merchandise_dict[$d_sn], 'barcode');
            }
            if(!array_key_exists((string)$c_service_id, $service_details_dict)){
                $service_details_dict[(string)$c_service_id] = [];
            }
            array_push($service_details_dict[(string)$c_service_id],$detail);
        }

        $sync_data = [];
        foreach($service_list as $service){
            $c_s_id = $service['id'];
            $c_s_details = $service_details_dict[(string)$c_s_id];
            $service['details'] = $c_s_details;
//            if($service['service_status']==ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN){
//                $service['status'] = ConstantConfig::STATUS_DELETE;
//            }
            array_push($sync_data, $service);
        }
        $c_sync_data = ['services'=>$sync_data];

        $order_client = new OrderClient();
        $res = $order_client->syncCustomerServiceData($c_sync_data);

        return $res;
    }

    /**
     * 作废
     * @param $service_ids
     * @param $user_info
     * @return array
     * @throws \yii\db\Exception
     */
    public function serviceCancel($service_ids, $user_info)
    {
        if(empty($service_ids) || empty($user_info)){
            return ['success'=>false, 'msg'=>'参数传递错误'];
        }
        //验证能否进行退换货单作废
        $c_customer_service = new CCustomerService();
        $validate_for_service  = $c_customer_service->validateServiceCancel($service_ids);
        if (empty($validate_for_service) || empty($validate_for_service['y'])) {
            return ['success' => false, 'msg' => '没有退换货单能被作废'];
        }
        $y_service_ids = $validate_for_service['y'];
        $y_return_order_ids = $validate_for_service['y_rd'];
        $y_exchange_order_ids = $validate_for_service['y_ed'];
        $n_service_ids = $validate_for_service['n'];

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $syn_data = ['orders' => [], 'customers' => []];
            $curr_at = time();

            $customer_service_model = new CustomerServiceModel();
            //修改退换货单状态
            $customer_service_model->setServiceStatus(ConstantConfig::SERVICE_STATUS_CANCEL);
            $customer_service_model->setAuditUserId($user_info['id']);
            $customer_service_model->setAuditUserName($user_info['name']);
            $customer_service_model->setUpdatedAt($curr_at);

            $res = $customer_service_model->updateServiceStatusByIds($y_service_ids);
            if (!$res) {
                throw new Exception('更改退换货单售后状态错误：作废失败');
            }
            foreach($y_service_ids as $y_s_id){
                $syn_data['customers'][] = ['service_id' => $y_s_id,
                    'service_status' => ConstantConfig::SERVICE_STATUS_CANCEL,
                    'can_cancel_examine' => ConstantConfig::CAN_CANCEL_EXAMINE,
                    'updated_at' => $curr_at];
            }


            //修改订单退换货标识
            $order_info_model = new OrderInfoModel();
            $order_info_model->setUpdatedAt(time());
            if($y_return_order_ids){
                foreach($y_return_order_ids as $y_o_id){
                    $syn_data['orders'][] = ['order_id' => $y_o_id,
                        'is_refund' => ConstantConfig::RETURN_FALSE,
                        'is_exchange' => ConstantConfig::EXCHANGE_FALSE,
                        'updated_at' => $curr_at];
                }
                $order_info_model->setIsRefund(ConstantConfig::RETURN_FALSE);
                $order_info_model->updateIsRefundByIds($y_return_order_ids);
            }
            if($y_exchange_order_ids){
                foreach($y_exchange_order_ids as $y_o_id){
                    $syn_data['orders'][] = ['order_id' => $y_o_id,
                        'is_refund' => ConstantConfig::RETURN_FALSE,
                        'is_exchange' => ConstantConfig::EXCHANGE_FALSE,
                        'updated_at' => $curr_at];
                }
                $order_info_model->setIsExchange(ConstantConfig::EXCHANGE_FALSE);
                $order_info_model->updateIsExchangeByIds($y_exchange_order_ids);
            }
            //订单退款状态变化和退款单状态变化
            $all_order_info = $order_info_model->getOrderInfoList($y_return_order_ids);
            $order_refund_model = new OrderRefundModel();
            $order_ids = [];
            $order_process_ids = [];
            $order_refund_ids = [];
            foreach ($all_order_info as $order_info) {
                $order_ids [] = $order_info['id'];
            }
            $order_refund_info_list = $order_refund_model->getOrderRefundInfoList($order_ids);
            foreach ($order_refund_info_list as $order_refund_detail){
                if ($order_refund_detail['is_apply'] == ConstantConfig::IS_APPLY_FALSE) {
                    $order_process_ids = $order_refund_detail['order_id'];
                    $order_refund_ids = $order_refund_detail['id'];
                }
            }
            if (!empty($order_process_ids)) {
                $order_res = $order_info_model->updateRefundStatus($order_process_ids, ConstantConfig::CANCEL_REFUND);
                if (!$order_res) {
                    throw new Exception('更改订单退款状态失败');
                }
            }
            if (!empty($order_refund_ids)) {
                $order_refund_res = $order_refund_model->updateRefundStatus($order_refund_ids, ConstantConfig::CANCEL_REFUND);
                if (!$order_refund_res) {
                    throw new Exception('更改退款单退款状态失败');
                }
            }
            //同步订单退换货标识及服务单售后状态至进销存系统
            $gearman_client_utils = new GearmanClientUtils();
            $result = $gearman_client_utils->syncOrderService($syn_data);
            if (!$result['success']) {
                throw new Exception($result['msg']);
            }

            $transaction->commit();

            //同步订单信息至PPC
            $gearman_client_utils = new GearmanClientUtils();
            $c_order_service = new COrderService();
            $order_ids = array_merge($y_return_order_ids, $y_exchange_order_ids);
            $orders = $c_order_service->getOrderInfoWithDetails($order_ids);
            $gearman_client_utils->syncOrderInfo2PPC($orders);

            //返回售后状态有误的退换货单
            $error_str = '';
            if (!empty($n_service_ids)) {
                $error_ids = join(',', $n_service_ids);
                $error_str = ',下列订单号售后状态错误：' . $error_ids;
            }
            return ['success' => True, 'msg' => '作废操作成功' . $error_str, 'r_order_ids' => $validate_for_service["y_rd"],
                'e_order_ids' => $validate_for_service["y_ed"]];

        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '作废失败：' . $e->getMessage()];
        }
    }

    /**
     * 修改service detail 信息： 修改商品编码， 修改规格代码，修改商品数量
     * @param $service_id
     * @param $service_detail_id
     * @param $field_type
     * @param $edit_field
     * @param $edit_value
     * @param $action_id
     * @param $action_name
     * @return array
     */
    public function editServiceDetail($service_id, $service_detail_id,$field_type, $edit_field, $edit_value, $action_id=0, $action_name=""){
        //得到要修改的service_id, service_detail_id
        if(empty($service_id) || empty($field_type) || empty($edit_field) || empty($edit_value)){
            return ['success'=>false, 'msg'=>'参数传递错误'];
        }

        //获取service 信息
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ['success'=>false, 'msg'=>'数据错误，没有当前service_id信息'];
        }
        //根据获取的service 表中的 service_type,判断是退货还是退换货.
//        $service_type = $service_info['service_type'];

        //获取service_detail 信息
        $customer_service_detail_model = new CustomerServiceDetailsModel();
        $customer_service_detail_model->setServiceId($service_id);
        $details_info = $customer_service_detail_model->findByServiceId();

        $detail_dict = [];
        $exist_m_str_list = [];
        foreach($details_info as $detail){
            $detail_dict[$detail['id']] = $detail;
            if($detail["return_type"] == $field_type){
                $d_sn = empty($detail["merchandise_specification_code"]) ? $detail["merchandise_sn"] : $detail["merchandise_specification_code"];
                $exist_m_str_list[] = $d_sn . "|" . $detail["merchandise_type"] . "|" . $detail["relation_key"];
            }
        }

        if ($service_detail_id > 0 && !array_key_exists($service_detail_id, $detail_dict)){
            return ['success'=>false, 'msg'=>'数据错误，获取的detail_id 不存在于原始数据中'];
        }elseif($service_detail_id > 0){
            $process_detail_info = $detail_dict[$service_detail_id];
            if (empty($process_detail_info)){
                return ['success'=>false, 'msg'=>'数据错误，获取customer_service_detail信息出错'];
            }
            if($process_detail_info['return_type']!=$field_type){
                return ['success'=>false, 'msg'=>'数据错误，获取的field_type和customer_detail信息不一致'];
            }
        }

        //获取订单详情
        $order_detail_model = new OrderDetailsModel();
        $order_details = $order_detail_model->getOrderDetails($service_info["order_id"]);
        $c_order_service = new COrderService();
        $order_grouped_details = $c_order_service->getOrderGroupedDetails($service_info["order_id"]);
        $order_details_dict = [];
        $order_details_sn_dict = [];
        foreach($order_details as &$detail){
            if((int)$detail['is_group'] == ConstantConfig::IS_GROUP_TRUE){
                $grouped_items = $order_grouped_details[$detail['id']];
                $group_original_price = 0.00;
                foreach($grouped_items as $gi){
                    $unit_price = floatval($gi['discount_price']) > 0 ? floatval($gi['discount_price']) : floatval($gi['sale_price']);
                    $group_original_price += $unit_price * intval($gi['numbers']);
                }
                $detail['grouped_items'] = $grouped_items;
                $detail['group_original_price'] = $group_original_price;
            }
            $order_details_dict[$detail["id"]] = $detail;
            $detail_sn = empty($detail["specification_code"]) ? $detail["merchandise_sn"] : $detail["specification_code"];
            $order_details_sn_dict[$detail_sn . "|" .$detail['merchandise_type']] = $detail;
        }
        $need_process_res = ""; //需编辑的退换货明细
        $new_detail_list = []; //需新增的退换货明细
        // 检验字段是否可编辑
        if(!in_array($edit_field, ['merchandise_sn', 'specification_code', 'new_merchandise_sn', 'new_specification_code', 'merchandise_type', 'numbers', 'unit_price'])){
            return ["success" => false, "msg" => "编辑字段不在允许编辑字段内"];
        }
        $edit_detail = empty($service_detail_id) ? [] : $detail_dict[$service_detail_id];

        $content = "";
        $merchandise_type_arr = ConstantConfig::merchandiseTypeStr();
        $return_type_str = (int)$field_type == ConstantConfig::RETURN_TYPE_BACK ? "退入" : "换出";
        if($edit_field == "merchandise_type"){
            //编辑商品类型 检测该类型商品是否已存在，计算赠品改为正常售卖后的价格，
            $sn = empty($edit_detail['merchandise_specification_code']) ? $edit_detail['merchandise_sn'] : $edit_detail['merchandise_specification_code'];
            $m_str = $sn . "|" . $edit_value . "|" . $edit_detail["relation_type"];
            if(in_array($m_str, $exist_m_str_list)){
                return ['success'=>false, 'msg'=>'数据错误，该商品已存在'];
            }
            $content = "编辑 ".$return_type_str."商品 ".$sn."商品类型" . $merchandise_type_arr[(int)$edit_detail["merchandise_type"]] . "为" . $merchandise_type_arr[(int)$edit_value];

            $need_process_res = $edit_detail;
            $need_process_res["merchandise_type"] = $edit_value;
            if((int)$edit_value == ConstantConfig::MERCHANDISE_TYPE_GIFT){
                $need_process_res["total_amount"] = 0.00;
            }else{
                $total_amount = (int)$need_process_res["numbers"] * floatval($need_process_res["unit_price"]);
                if(!empty($edit_detail["order_detail_id"]) and array_key_exists($edit_detail["order_detail_id"], $order_details_dict)){
                    $order_detail_price = $order_details_dict[$edit_detail["order_detail_id"]]["pay_price"];
                    $total_amount = $total_amount > floatval($order_detail_price) ? floatval($order_detail_price) : $total_amount;
                }
                $need_process_res["total_amount"] = $total_amount;
            }
        }elseif($edit_field == "numbers") {
            $need_process_res = $edit_detail;
            $sn = empty($edit_detail['merchandise_specification_code']) ? $edit_detail['merchandise_sn'] : $edit_detail['merchandise_specification_code'];
            $content = "编辑 ".$return_type_str."商品 ".$sn."数量" . $edit_detail["numbers"] . "为" . $edit_value;
            $need_process_res["numbers"] = $edit_value;
            if((int)$need_process_res["merchandise_type"] == ConstantConfig::MERCHANDISE_TYPE_GIFT){
                $total_amount = 0.00;
            }else{
                $total_amount = (int)$edit_value * floatval($edit_detail["unit_price"]);
                if(!empty($edit_detail["order_detail_id"]) and array_key_exists($edit_detail["order_detail_id"], $order_details_dict)){
                    $order_detail_price = $order_details_dict[$edit_detail["order_detail_id"]]["pay_price"];
                    $total_amount = $total_amount > floatval($order_detail_price) ? floatval($order_detail_price) : $total_amount;
                }
            }
            $need_process_res["total_amount"] = $total_amount;
        }elseif($edit_field == "unit_price") {
            $need_process_res = $edit_detail;
            $sn = empty($edit_detail['merchandise_specification_code']) ? $edit_detail['merchandise_sn'] : $edit_detail['merchandise_specification_code'];
            $content = "编辑 ".$return_type_str."商品 ".$sn."单价" . $edit_detail["unit_price"] . "为" . $edit_value;
            $need_process_res["unit_price"] = $edit_value;
            if((int)$need_process_res["merchandise_type"] == ConstantConfig::MERCHANDISE_TYPE_GIFT){
                $total_amount = 0.00;
            }else{
                $total_amount = (int)$edit_detail['numbers'] * floatval($edit_value);
                if(!empty($edit_detail["order_detail_id"]) and array_key_exists($edit_detail["order_detail_id"], $order_details_dict)){
                    $order_detail_price = $order_details_dict[$edit_detail["order_detail_id"]]["pay_price"];
                    $total_amount = $total_amount > floatval($order_detail_price) ? floatval($order_detail_price) : $total_amount;
                }
            }
            $need_process_res["total_amount"] = $total_amount;
        }elseif(in_array($edit_field, ['merchandise_sn', 'specification_code', 'new_merchandise_sn', 'new_specification_code'])){
            //新增或编辑规格号或货号
            $old_value = ArrayUtil::getVal($edit_detail, $edit_field, "空");
            $content = "编辑 ".$return_type_str."商品 修改". $old_value . "为" . $edit_value;
            if(in_array($edit_field, ['new_merchandise_sn', 'new_specification_code'])){
                $content = "编辑 ".$return_type_str."商品 新增" . $edit_value;
            }

            $edit_value = strtoupper($edit_value);
            $merchandise_type = empty($edit_detail) ? ConstantConfig::MERCHANDISE_TYPE_DEFAULT : $edit_detail["merchandise_type"];
            if($edit_field == "new_merchandise_sn" or $edit_field == "new_specification_code"){
                //当新增货号或规格号时，需传递售卖类型参数 edit_value为sn|merchandise_type
                $m_arr = explode("|", $edit_value);
                $edit_value = $m_arr[0];
                $merchandise_type = count($m_arr) > 1 ? $m_arr[1] : ConstantConfig::MERCHANDISE_TYPE_DEFAULT;
            }
            $m_str = $edit_value . "|" . $merchandise_type . "|";
            if(in_array($m_str, $exist_m_str_list)){
                return ['success'=>false, 'msg'=>'数据错误，该类型商品已存在'];
            }
            $relation_type = ConstantConfig::GROUP_ITEMS_RELATION_SN;
            if($edit_field == "specification_code" or $edit_field == "new_specification_code"){
                $relation_type = ConstantConfig::GROUP_ITEMS_RELATION_SPECIFICATION_CODE;
            }

            //检索编辑或新增商品是否在原商品中
            if(array_key_exists($edit_value . "|" . $merchandise_type, $order_details_sn_dict)){
                //为原订单商品
                $o_detail = $order_details_sn_dict[$edit_value . "|" . $merchandise_type];
                if(array_key_exists("grouped_items", $o_detail)){
                    //检测组合商品是否都存在
                    $detail_unit_price = !floatval($o_detail["discount_price"])>0 ? $o_detail["sale_price"] : $o_detail["discount_price"];
                    foreach($o_detail["grouped_items"] as $gi){
                        $g_m_sn = empty($gi["specification_code"]) ? $gi["merchandise_sn"] : $gi["specification_code"];
                        if(in_array($g_m_sn."|".$merchandise_type."|".$edit_value, $exist_m_str_list)){
                            continue;
                        }
                        $gi_unit_price = !floatval($gi["discount_price"])>0 ? $gi["sale_price"] : $gi["discount_price"];
                        $unit_price = floatval($o_detail["group_original_price"]) != 0 ? sprintf('%.2f', $detail_unit_price * (floatval($gi_unit_price) / floatval($o_detail["group_original_price"]))) : 0.00;
                        if($service_info["service_type"] == ConstantConfig::SERVICE_TYPE_RETURN and $unit_price != 0){//退货
                            $unit_price = floatval(sprintf('%.2f', floatval($unit_price) * (floatval($detail_unit_price) / floatval($o_detail["group_original_price"]))));
                        }

                        $total_amount = 0.00;
                        if((int)$merchandise_type != ConstantConfig::MERCHANDISE_TYPE_GIFT){
                            $total_amount = (int)$gi["numbers"] * floatval($unit_price);
                            if($field_type == ConstantConfig::RETURN_TYPE_BACK and $total_amount > floatval($o_detail["pay_price"])){
                                $total_amount = floatval($o_detail["pay_price"]);
                            }
                        }
                        $new_detail_list[] = [
                            "service_id"=>$service_id,
                            "service_type" => $service_info["service_type"],
                            "order_id" => $service_info["order_id"],
                            "order_sn" => $service_info["order_sn"],
                            "order_detail_id" => $o_detail["id"],
                            "return_type" => $field_type,
                            "merchandise_id" => $gi["merchandise_id"],
                            "merchandise_sn" => $gi["merchandise_sn"],
                            "merchandise_name" => $gi["merchandise_name"],
                            "merchandise_specification_id" => $gi["specification_id"],
                            "merchandise_specification_code" => $gi["specification_code"],
                            "merchandise_specification_name" => $gi["specification_name"],
                            "merchandise_type" => $merchandise_type,
                            "relation_type" => $relation_type,
                            "relation_key" => $edit_value,
                            "numbers" => $gi["numbers"],
                            "unit_price" => $unit_price,
                            "total_amount" => $total_amount
                        ];
                    }
                    if(empty($new_detail_list)){
                        return ["success" => false, "msg" => "数据错误，该类型商品已存在"];
                    }

                }else{
                    //编辑或新增
                    if((int)$merchandise_type == ConstantConfig::MERCHANDISE_TYPE_GIFT){
                        $unit_price = !floatval($o_detail["discount_price"])>0 ? $o_detail["sale_price"] : $o_detail["discount_price"];
                    }else{
                        $unit_price = $o_detail["pay_price"];
                    }

                    $total_amount = 0.00;
                    if($edit_field == "merchandise_sn" or $edit_field == "specification_code"){
                        //编辑服务详情
                        $need_process_res = $edit_detail;
                        $need_process_res["merchandise_id"] = $o_detail["merchandise_id"];
                        $need_process_res["merchandise_sn"] = $o_detail["merchandise_sn"];
                        $need_process_res["merchandise_name"] = $o_detail["merchandise_name"];
                        $need_process_res["merchandise_specification_id"] = $o_detail["specification_id"];
                        $need_process_res["merchandise_specification_code"] = $o_detail["specification_code"];
                        $need_process_res["merchandise_specification_name"] = $o_detail["specification_name"];
                        $need_process_res["relation_type"] = 0;
                        $need_process_res["relation_key"] = "";
                        $need_process_res["unit_price"] = $unit_price;

                        if((int)$merchandise_type != ConstantConfig::MERCHANDISE_TYPE_GIFT){
                            $total_amount = floatval($unit_price) * intval($need_process_res["numbers"]);
                            if($field_type == ConstantConfig::RETURN_TYPE_BACK and $total_amount > floatval($o_detail["pay_price"])){
                                $total_amount = floatval($o_detail["pay_price"]);
                            }
                        }
                        $need_process_res["total_amount"] = $total_amount;
                    }else{
                        //新增货号或规格号
                        if((int)$merchandise_type != ConstantConfig::MERCHANDISE_TYPE_GIFT){
                            $total_amount = (int)$o_detail["numbers"] * floatval($unit_price);
                            if($field_type == ConstantConfig::RETURN_TYPE_BACK and $total_amount > floatval($o_detail["pay_price"])){
                                $total_amount = floatval($o_detail["pay_price"]);
                            }
                        }
                        $new_detail_list[] = [
                            "service_id"=>$service_id,
                            "service_type" => $service_info["service_type"],
                            "order_id" => $service_info["order_id"],
                            "order_sn" => $service_info["order_sn"],
                            "order_detail_id" => $o_detail["id"],
                            "return_type" => $field_type,
                            "merchandise_id" => $o_detail["merchandise_id"],
                            "merchandise_sn" => $o_detail["merchandise_sn"],
                            "merchandise_name" => $o_detail["merchandise_name"],
                            "merchandise_specification_id" => $o_detail["specification_id"],
                            "merchandise_specification_code" => $o_detail["specification_code"],
                            "merchandise_specification_name" => $o_detail["specification_name"],
                            "merchandise_type" => $merchandise_type,
                            "relation_type" => 0,
                            "relation_key" => "",
                            "numbers" => $o_detail["numbers"],
                            "unit_price" => $unit_price,
                            "total_amount" => $total_amount,
                        ];
                    }
                }
            }else{//原订单商品无该货号或规格号
                //检索商品信息
                $c_merchandise_service = new CMerchandiseService();
                if($edit_field == "merchandise_sn" or $edit_field == "new_merchandise_sn"){
                    $merchandise_result = $c_merchandise_service->getMerchandiseInfoBySN($edit_value, $service_info["store_id"]);
                }else{
                    $merchandise_result = $c_merchandise_service->getSpecificationInfoByCode($edit_value, $service_info["store_id"]);
                }
                if(!$merchandise_result["success"]){
                    return $merchandise_result;
                }
                $merchandise_info = $merchandise_result["data"];
                if(($edit_field == "merchandise_sn" or $edit_detail == "new_merchandise_sn") and array_key_exists("specification_code", $merchandise_info)){
                    return ["success" => "false", "msg" => "数据错误，缺少规格信息"];
                }
                $merchandise_price = floatval($merchandise_info["discount_price"])>0 ? $merchandise_info["discount_price"] : $merchandise_info["price"];
                if((int)$merchandise_info["is_group"] == ConstantConfig::IS_GROUP_TRUE){
                    $grouped_items = $merchandise_info["grouped_items"];
                    $grouped_original_price = 0.00;
                    foreach($grouped_items as $value){
                        $grouped_original_price += floatval($value["unit_price"]) * (int)$value["numbers"];
                    }
                    foreach($grouped_items as $gi){
                        $g_m_sn = empty($gi["merchandise_specification_code"]) ? $gi["merchandise_sn"] : $gi["merchandise_specification_code"];
                        if(in_array($g_m_sn."|".$merchandise_type."|".$edit_value, $exist_m_str_list)){
                            continue;
                        }
                        $unit_price = floatval($gi["discount_price"])>0 ? $gi["discount_price"] : $gi["price"];
                        $total_amount = 0;
                        if($field_type == ConstantConfig::RETURN_TYPE_BACK and $unit_price != 0){//退入
                            $unit_price = $grouped_original_price == 0 ? 0.00 : $unit_price * ($merchandise_price / $grouped_original_price);
                        }
                        // 退货商品中新增原订单中无的商品，订单金额为0
                        if($field_type == ConstantConfig::RETURN_TYPE_SWAPPED and (int)$merchandise_type != ConstantConfig::MERCHANDISE_TYPE_GIFT){
                            $total_amount = (int)$gi["numbers"] * floatval($unit_price);
                        }
                        $new_detail_list[] = [
                            "service_id"=>$service_id,
                            "service_type" => $service_info["service_type"],
                            "order_id" => $service_info["order_id"],
                            "order_sn" => $service_info["order_sn"],
                            "order_detail_id" => 0,
                            "return_type" => $field_type,
                            "merchandise_id" => $gi["merchandise_id"],
                            "merchandise_sn" => $gi["merchandise_sn"],
                            "merchandise_name" => $gi["merchandise_name"],
                            "merchandise_specification_id" => $gi["merchandise_specification_id"],
                            "merchandise_specification_code" => $gi["merchandise_specification_code"],
                            "merchandise_specification_name" => $gi["merchandise_specification_name"],
                            "merchandise_type" => $merchandise_type,
                            "relation_type" => $relation_type,
                            "relation_key" => $edit_value,
                            "numbers" => $gi["numbers"],
                            "unit_price" => $unit_price,
                            "total_amount" => $total_amount
                        ];
                    }
                    if(empty($new_detail_list)){
                        return ["success" => false, "msg" => "数据错误，该类型商品已存在"];
                    }
                }else{
                    if($edit_field == "merchandise_sn" or $edit_field == "new_merchandise_sn"){
                        $merchandise_id = $merchandise_info["id"];
                        $merchandise_sn = $edit_value;
                        $merchandise_name = $merchandise_info["merchandise_name"];
                        $merchandise_specification_id = 0;
                        $merchandise_specification_code = "";
                        $merchandise_specification_name = "";
                    }else{
                        $merchandise_id = $merchandise_info["merchandise_id"];
                        $merchandise_sn = $merchandise_info["merchandise_sn"];
                        $merchandise_name = $merchandise_info["merchandise_name"];
                        $merchandise_specification_id = $merchandise_info["id"];
                        $merchandise_specification_code = $edit_value;
                        $merchandise_specification_name = $merchandise_info["merchandise_specification_name"];
                    }

                    if($edit_field == "merchandise_sn" or $edit_field == "specification_code") {
                        //编辑服务详情
                        $need_process_res = $edit_detail;
                        $need_process_res["merchandise_id"] = $merchandise_id;
                        $need_process_res["merchandise_sn"] = $merchandise_sn;
                        $need_process_res["merchandise_name"] = $merchandise_name;
                        $need_process_res["merchandise_specification_id"] = $merchandise_specification_id;
                        $need_process_res["merchandise_specification_code"] = $merchandise_specification_code;
                        $need_process_res["merchandise_specification_name"] = $merchandise_specification_name;
                        $need_process_res["relation_type"] = 0;
                        $need_process_res["relation_key"] = "";
                        $need_process_res["unit_price"] = $merchandise_price;
                        $total_amount = 0.00;
                        if((int)$merchandise_type != ConstantConfig::MERCHANDISE_TYPE_GIFT){
                            $total_amount = floatval($need_process_res["unit_price"]) * intval($need_process_res["numbers"]);
                            if($edit_detail and !empty($edit_detail["order_detail_id"]) and array_key_exists($edit_detail["order_detail_id"], $order_details_dict)){
                                $order_detail = $order_details_dict[$edit_detail["order_detail_id"]];
                                $order_detail_all_price = floatval($order_detail["pay_price"]) * (int)$order_detail["numbers"];
                                $total_amount = $total_amount > $order_detail_all_price ? $order_detail_all_price : $total_amount;
                            }
                        }
                        $need_process_res["total_amount"] = $total_amount;
                    }else{
                        //新增货号或规格号
                        $total_amount = 0.00;
                        if((int)$merchandise_type != ConstantConfig::MERCHANDISE_TYPE_GIFT and $field_type == ConstantConfig::RETURN_TYPE_SWAPPED) {
                            $total_amount = 1 * floatval($merchandise_price);
                        }
                        $new_detail_list[] = [
                            "service_id"=>$service_id,
                            "service_type" => $service_info["service_type"],
                            "order_id" => $service_info["order_id"],
                            "order_sn" => $service_info["order_sn"],
                            "order_detail_id" => 0,
                            "return_type" => $field_type,
                            "merchandise_id" => $merchandise_id,
                            "merchandise_sn" => $merchandise_sn,
                            "merchandise_name" => $merchandise_name,
                            "merchandise_specification_id" => $merchandise_specification_id,
                            "merchandise_specification_code" => $merchandise_specification_code,
                            "merchandise_specification_name" => $merchandise_specification_name,
                            "merchandise_type" => $merchandise_type,
                            "relation_type" => 0,
                            "relation_key" => "",
                            "numbers" => 1,
                            "unit_price" => $merchandise_price,
                            "total_amount" => $total_amount
                        ];
                    }
                }
            }
        }

        if(empty($need_process_res) and empty($new_detail_list)){
            return ['success'=>false, 'msg'=>'数据处理错误'];
        }
        //计算退款金额 及换货支付差额代收货款
        $in_merchandise_price = 0.00;
        $out_merchandise_price = 0.00;
        //未改动商品金额
        foreach($details_info as $detail){
            $cur_return_type = (int)$detail['return_type'];
            if(!empty($service_detail_id) and (int)$detail["id"] == (int)$service_detail_id){//编辑项
                continue;
            }
            if($cur_return_type == ConstantConfig::RETURN_TYPE_BACK){
                $in_merchandise_price += floatval($detail["total_amount"]);
            }else{
                $out_merchandise_price += floatval($detail["total_amount"]);
            }
        }
        //改动项金额
        $additional_price = 0.00;
        if(!empty($need_process_res)){
            $additional_price += floatval($need_process_res["total_amount"]);
        }
        if(!empty($new_detail_list)){
            foreach($new_detail_list as $nd){
                $additional_price += floatval($nd["total_amount"]);
            }
        }
        if((int)$field_type == ConstantConfig::RETURN_TYPE_BACK){
            $in_merchandise_price += $additional_price;
        }elseif((int)$field_type == ConstantConfig::RETURN_TYPE_SWAPPED){
            $out_merchandise_price += $additional_price;
        }
        //计算退款金额或支付差额
        $refund_distribution_price = floatval($service_info['refund_distribution_price']);
        $need_update_pay_status = false;
        $pay_status = 0;
        if((int)$service_info["service_type"] == ConstantConfig::SERVICE_TYPE_RETURN){//退货
            if((int)$service_info["is_refund"] == ConstantConfig::REFUND_TRUE){
                $refund_price = $in_merchandise_price + $refund_distribution_price;
            }else{
                $refund_price = 0.00;
            }
            $service_price = $refund_price;
            $c_margin_price = 0.00;
            $shipping_price = 0.00;
        }else{//换货
            $c_margin_price = $out_merchandise_price - $in_merchandise_price;
            $refund_price = 0.00;
            if($c_margin_price < 0){
                $c_margin_price = 0.00;
                if((int)$service_info["is_refund"] == ConstantConfig::REFUND_TRUE) {
                    $refund_price = abs($c_margin_price) + $refund_distribution_price;
                }
            }
            $shipping_price = floatval($service_info["shipping_price"]);
            //margin_price + shipping_price + distribution_price
            $service_price = $c_margin_price + $shipping_price + floatval($service_info['distribution_price']);

            // 换货单维护付款状态的变更
            //当代收货款>0时 货到付款已付款需变更为未付款
            if($service_price > 0 and $service_info['pay_type'] == ConstantConfig::PAY_TYPE_OFFLINE
                and $service_info['pay_status'] != ConstantConfig::PAY_STATUS_UNPAID){
                $need_update_pay_status = true;
                $pay_status = ConstantConfig::PAY_STATUS_UNPAID;
            }
            //当代收货款等于0时 货到付款未付款需变更为已付款
            if(floatval($service_price) == 0 and $service_info['pay_type'] == ConstantConfig::PAY_TYPE_OFFLINE
                and $service_info['pay_status'] != ConstantConfig::PAY_STATUS_PAID){
                $need_update_pay_status = true;
                $pay_status = ConstantConfig::PAY_STATUS_PAID;
            }
        }

        $new_detail = new CustomerServiceDetailsModel();
        $cur_time = time();
        //修改 service_detail 信息
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            //编辑服务明细
            $is_total_amount = true;
            if((int)$service_info["service_type"] == ConstantConfig::SERVICE_TYPE_RETURN and $service_info["is_refund"] == ConstantConfig::REFUND_FALSE){
                $is_total_amount = false;
            }
            $merchandise_type_arr = ConstantConfig::merchandiseTypeStr();
            if(!empty($need_process_res)){
                $new_detail->setId($service_detail_id);
                $new_detail->setMerchandiseId($need_process_res['merchandise_id']);
                $new_detail->setMerchandiseSn($need_process_res['merchandise_sn']);
                $new_detail->setMerchandiseName($need_process_res['merchandise_name']);
                $new_detail->setMerchandiseSpecificationId($need_process_res['merchandise_specification_id']);
                $new_detail->setMerchandiseSpecificationCode($need_process_res['merchandise_specification_code']);
                $new_detail->setMerchandiseSpecificationName($need_process_res['merchandise_specification_name']);
                $new_detail->setMerchandiseType($need_process_res['merchandise_type']);
                $new_detail->setRelationType($need_process_res["relation_type"]);
                $new_detail->setRelationKey($need_process_res["relation_key"]);
                $new_detail->setNumbers($need_process_res['numbers']);
                $new_detail->setUnitPrice($need_process_res['unit_price']);
                $new_detail->setTotalAmount($is_total_amount ? $need_process_res["total_amount"] : 0.00);
                $new_detail->setUpdatedAt($cur_time);

                $process_res = $new_detail->updateServiceDetail();
                if(!$process_res){
                    throw new Exception(' 订单明细操作失败 ');
                }
                $need_process_res["merchandise_type_str"] = $merchandise_type_arr[(int)$need_process_res["merchandise_type"]];
            }
            //新增服务明细
            if(!empty($new_detail_list)){
                $new_detail = new CustomerServiceDetailsModel();
                //,service_id,service_type,order_id,order_sn,return_type, //order_detail_id 暂时设置为空
                $command = $new_detail->createBatch();
                foreach($new_detail_list as &$value){
                    $new_detail->setServiceId($service_id);
                    $new_detail->setServiceType($service_info['service_type']);
                    $new_detail->setOrderId($service_info['order_id']);
                    $new_detail->setOrderSn($service_info['order_sn']);
                    $new_detail->setReturnType($field_type);
                    $new_detail->setOrderDetailId(0);
                    $new_detail->setMerchandiseId($value['merchandise_id']);
                    $new_detail->setMerchandiseSn($value['merchandise_sn']);
                    $new_detail->setMerchandiseName($value['merchandise_name']);
                    $new_detail->setMerchandiseSpecificationId($value['merchandise_specification_id']);
                    $new_detail->setMerchandiseSpecificationCode($value['merchandise_specification_code']);
                    $new_detail->setMerchandiseSpecificationName($value['merchandise_specification_name']);
                    $new_detail->setMerchandiseType($value['merchandise_type']);
                    $new_detail->setRelationType($value["relation_type"]);
                    $new_detail->setRelationKey($value["relation_key"]);
                    $new_detail->setNumbers($value['numbers']);
                    $new_detail->setUnitPrice($value['unit_price']);
                    $new_detail->setTotalAmount($is_total_amount ? $value["total_amount"] : 0.00);
                    $new_detail->setStatus(0);
                    $new_detail->setUpdatedAt($cur_time);
                    $new_detail->setCreatedAt($cur_time);
                    $new_detail->createBatchExecute($command);
                    $value["id"] = $connection->getLastInsertID();
                    $value["merchandise_type_str"] = $merchandise_type_arr[(int)$value["merchandise_type"]];
                }
            }
            //对customer_service 进行数据处理
            $customer_service_model = new CustomerServiceModel();
            $customer_service_model->setUpdatedAt($cur_time);
            $customer_service_model->setRefundPrice($refund_price);
            $customer_service_model->setMarginPrice($c_margin_price);
            $customer_service_model->setBonus(0);
            $customer_service_model->setShippingPrice($shipping_price);
            $customer_service_model->setServicePrice($service_price);
            $customer_service_model->setId($service_id);
            $update_res = $customer_service_model->updateServiceAllPriceById();
            if(empty($update_res)){
                throw new Exception('编辑退换货条目信息时,customer service 价格处理错误');
            }

            //更新付款状态
            if($need_update_pay_status){
                $customer_service_model->setId($service_id);
                $customer_service_model->setPayStatus($pay_status);
                $customer_service_model->setUpdatedAt($cur_time);
                $customer_service_model->updatePayStatus();
            }

            //操作日志
            $c_log_service = new CLogService();
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"],
                $action_id, $action_name, $content);

            $transaction->commit();
            return ['success'=>true, 'msg'=>'操作成功', 'data'=>$need_process_res, "new_details"=>$new_detail_list];
        }catch (Exception $e){
            $transaction->rollBack();
            return ['success'=>false, 'msg'=>'系统错误: '.$e->getMessage()];
        }

    }

    /**
     * 删除退换货明细
     * @param $service_id
     * @param $detail_id
     * @param int $action_id
     * @param string $action_name
     * @return array
     * @throws \yii\db\Exception
     */
    public function deleteServiceDetail($service_id, $detail_id, $action_id=0, $action_name=""){
        if(empty($service_id) || empty($detail_id)){
            return ['success'=> false, 'msg'=>'参数传递错误,缺少必要的参数'];
        }

        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ['success'=>false, 'msg'=>'数据错误，service信息不存在或已删除'];
        }

        //获取service_detail 信息
        $customer_service_detail_model = new CustomerServiceDetailsModel();
        $customer_service_detail_model->setServiceId($service_id);
        $details_info = $customer_service_detail_model->findByServiceId();

        //计算退入、换出金额
        $in_merchandise_price = 0.00;
        $out_merchandise_price = 0.00;
        $delete_detail = [];
        foreach($details_info as $detail){
            $cur_return_type = $detail['return_type'];
            if ((int)$detail['id'] == $detail_id ) { // 被修改项
                $delete_detail = $detail;
            }else{
                if($detail["merchandise_type"] == ConstantConfig::MERCHANDISE_TYPE_GIFT){
                    continue;
                }
                if($cur_return_type == ConstantConfig::RETURN_TYPE_BACK){
                    $in_merchandise_price += floatval($detail["total_amount"]);
                }else{
                    $out_merchandise_price += floatval($detail["total_amount"]);
                }
            }
        }
        if(empty($delete_detail)){
            return ['success'=>false, 'msg'=>'数据错误，获取的detail_id 不存在于原始数据中'];
        }

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        $cur_time = time();
        try{
            $customer_service_detail_model = new CustomerServiceDetailsModel();
            $customer_service_detail_model->setId($detail_id);
            $del_process_res = $customer_service_detail_model->deleteServiceDetailById();

            if (!$del_process_res){
                throw new Exception('删除退换货明细数据错误');
            }

            //对customer_service 进行数据处理
            $customer_service_model = new CustomerServiceModel();
            $customer_service_model->setUpdatedAt($cur_time);
            $refund_price = 0.00;
            $need_update_pay_status = false;
            $pay_status = 0;
            if((int)$service_info["service_type"] == ConstantConfig::SERVICE_TYPE_EXCHANGE){
                $c_margin_price = $out_merchandise_price - $in_merchandise_price;
                if(floatval($c_margin_price) < 0){
//                    throw new Exception('删除退换货明细数据错误，换出商品总额不能小于退入商品总额');
                    if((int)$service_info['is_refund'] == ConstantConfig::REFUND_TRUE){
                        $refund_price = abs($c_margin_price) + floatval($service_info['refund_distribution_price']);
                    }
                    $c_margin_price = 0;
                }
                $shipping_price = $service_info["shipping_price"];
                //代收货款=支付差额+物流费用+分销价
                $service_price = $c_margin_price + floatval($shipping_price) + floatval($service_info['distribution_price']);

                // 换货单维护付款状态的变更
                //当代收货款>0时 货到付款已付款需变更为未付款
                if($service_price > 0 and $service_info['pay_type'] == ConstantConfig::PAY_TYPE_OFFLINE
                    and $service_info['pay_status'] != ConstantConfig::PAY_STATUS_UNPAID){
                    $need_update_pay_status = true;
                    $pay_status = ConstantConfig::PAY_STATUS_UNPAID;
                }
                //当代收货款等于0时 货到付款未付款需变更为未付款
                if(floatval($service_price) == 0 and $service_info['pay_type'] == ConstantConfig::PAY_TYPE_OFFLINE
                    and $service_info['pay_status'] != ConstantConfig::PAY_STATUS_PAID){
                    $need_update_pay_status = true;
                    $pay_status = ConstantConfig::PAY_STATUS_PAID;
                }
            }else{
                $c_margin_price = 0;
                $service_price = $in_merchandise_price;
                if((int)$service_info['is_refund'] == ConstantConfig::REFUND_TRUE){
                    $refund_price = $in_merchandise_price + floatval($service_info['refund_distribution_price']);
                }
                $shipping_price = 0;
            }
            $customer_service_model->setRefundPrice($refund_price);
            $customer_service_model->setMarginPrice($c_margin_price);
            $customer_service_model->setBonus(0);
            $customer_service_model->setShippingPrice($shipping_price);
            $customer_service_model->setServicePrice($service_price);
            $customer_service_model->setId($service_id);
            $update_res = $customer_service_model->updateServiceAllPriceById();
            if(empty($update_res)){
                throw new Exception('删除退换货信息时,customer service 价格处理错误');
            }

            //更新付款状态
            if($need_update_pay_status){
                $customer_service_model->setId($service_id);
                $customer_service_model->setPayStatus($pay_status);
                $customer_service_model->setUpdatedAt($cur_time);
                $customer_service_model->updatePayStatus();
            }

            //操作日志记录
            $c_log_service = new CLogService();
            $d_sn = empty($delete_detail["specification_code"]) ? $delete_detail["merchandise_sn"] : $delete_detail["specification_code"];
            $edit_type = (int)$delete_detail["return_type"] == ConstantConfig::RETURN_TYPE_BACK ? "退入" : "换出";
            $content = "编辑 " . $edit_type. "商品 删除" . $d_sn;
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id,
                $action_name, $content);

            $transaction->commit();
            return ['success'=>true, 'msg'=>'操作成功'];
        }catch (Exception $e){
            $transaction->rollBack();
            return ['success'=>false, 'msg'=>'系统错误:'.$e->getMessage()];
        }

    }

    /**
     * 修改退款原因
     * @param $service_id
     * @param $refund_reason
     * @param int $action_id
     * @param string $action_name
     * @return array
     */
    public function updateRefundReason($service_id, $refund_reason, $action_id=0, $action_name="")
    {

        if (empty($service_id)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }

        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($service_id);
        $service_info = $customer_service_model->findByPk();
        if(empty($service_info)){
            return ["success" => false, "msg" => "退换货单信息获取失败"];
        }
        $order_id = $service_info['order_id'];
        if($service_info['service_status'] != ConstantConfig::SERVICE_STATUS_WAITING_BUYER_RETURN ||
            $service_info['is_refund'] == ConstantConfig::REFUND_FALSE ||
            $service_info['service_type']!=ConstantConfig::SERVICE_TYPE_RETURN){
            return ['success'=>false, 'msg'=>'该订单不能编辑'];
        }
        $customer_service_model->updateFieldById($service_id, "refund_reason", $refund_reason);
        //修改退款单的退款原因
        $order_refund_model = new OrderRefundModel();
        $order_refund_info = $order_refund_model->getOrderRefundInfo($order_id);
        if(empty($order_refund_info) or $order_refund_info['refund_status'] == ConstantConfig::CANCEL_REFUND){
            return ["success" => false, "msg" => "退款单信息获取失败"];
        }
        $order_refund_id = $order_refund_info['id'];
        $order_refund_model->updateFieldByOrderId($order_refund_id,'refund_reason', $refund_reason);
        //保存操作日志
        $c_log_service = new CLogService();
        $content = "编辑 退款原因 " . $service_info["refund_reason"] . "为" . $refund_reason;
        $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $order_id, $action_id, $action_name, $content,
            "refund_reason", $service_info["refund_reason"], $refund_reason);
        return ['success'=>true, 'msg'=>'修改成功'];
    }

    /**
     * 修改退款类型
     * @param $id
     * @param $refund_type
     * @param int $action_id
     * @param string $action_name
     * @return array
     * @throws Exception
     */

    public function updateServiceRefundType($id, $refund_type, $action_id=0, $action_name="")
    {
        if (empty($id) || empty($refund_type)){
            return ['success'=>false, 'msg'=>'参数传递不全'];
        }
        $customer_service_model = new CustomerServiceModel();
        $customer_service_model->setId($id);
        #获取退货单信息
        $service_info = $customer_service_model->findByPk();
        if($service_info['is_refund'] == ConstantConfig::REFUND_FALSE)
        {
            return ['success'=>false, 'msg'=>'不能修改该单的退款类型'];
        }
        $customer_service_model->setRefundType($refund_type);
        $result = $customer_service_model->updateRefundType();
        //修改退货单信息
        $order_id = $service_info['order_id'];
        $order_refund_model = new OrderRefundModel();
        $order_refund_info = $order_refund_model->getOrderRefundInfo($order_id);
        if (empty($order_refund_info) or $order_refund_info['refund_status'] == ConstantConfig::CANCEL_REFUND) {
            return ["success" => false, "msg" => "退款单信息获取失败"];
        }
        $order_refund_model ->setRefundType($refund_type);
        $order_refund_model ->setId($order_refund_info['id']);
        $refund_result = $order_refund_model->updateRefundType();
        if(!$refund_result){
            throw new Exception('更新退款单退款方式失败');
        }

        if ($result) {
            //保存操作日志
            $c_log_service = new CLogService();
            $refund_type_arr = ConstantConfig::refundAllType();
            $old_value = ArrayUtil::getVal($refund_type_arr, $service_info["refund_type"], $service_info["refund_type"]);
            $new_value = ArrayUtil::getVal($refund_type_arr, $refund_type, $refund_type);
            $content = "编辑 退货 退款类型" . $old_value . "为" . $new_value;
            $c_log_service->saveAttributeActionLogs(ConstantConfig::RESOURCE_ORDER, $service_info["order_id"], $action_id, $action_name, $content,
                "refund_type", $service_info["refund_type"], $refund_type);
            return ['success' => true, 'msg' => '修改成功'];
        } else {
            return ['success' => false, 'msg' => '修改失败'];
        }
    }
}