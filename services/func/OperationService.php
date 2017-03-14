<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 7/31/15
 * Time: 11:26 AM
 */

namespace app\services\func;

use app\models\OperationItemsModel;
use app\models\OperationsModel;
use app\util\ConstantConfig;
use app\services\operation\COperationService;
use app\services\operation\COperationItemService;
use app\util\RedisUtil;
use Exception;
use Yii;

class OperationService {
    /**
     * 根据operation_key获得所有子项数据
     * @param $operation_key
     * @return array
     */
    public function getOperationItems($operation_key){
        if (empty($operation_key)){
            return [];
        }
        $operation_service = new COperationService();
        $operation_info = $operation_service->getOperationInfo($operation_key);
        if (empty($operation_info)){
            return [];
        }

        $operation_id = $operation_info['id'];
        $operation_item_service = new COperationItemService();
        $operation_items = $operation_item_service->getOperationItemsByOperationId(intval($operation_id));

        if($operation_key == ConstantConfig::ORDER_CANCEL_REASON_STR || $operation_key == ConstantConfig::ORDER_REJECT_REASON_STR) {

            // 对原因进行排序
            $mark_cancel_key = '';
            $mark_cancel_reason = '';
            $mark_ceshi_key = '';
            $mark_ceshi_reason = '';
            $mark_quhuo_reason = '';
            $mark_quhuo_key = '';
            $mark_eyi_reason = '';
            $mark_eyi_key = '';
            foreach ($operation_items as $key=>$value) {
                $reason = $value['item_name'];
                if($reason == '缺货取消') {
                    $mark_quhuo_reason = $operation_items[$key];
                    $mark_quhuo_key = $key;
                } elseif ($reason == '恶意下单') {
                    $mark_eyi_reason = $operation_items[$key];
                    $mark_eyi_key = $key;
                } elseif( $reason == '取消标记') {
                    $mark_cancel_reason = $operation_items[$key];
                    $mark_cancel_key = $key;
                } elseif ($reason == '测试') {
                    $mark_ceshi_reason = $operation_items[$key];
                    $mark_ceshi_key = $key;
                }
            }

            if ($mark_quhuo_reason) {
                unset($operation_items[$mark_quhuo_key]);
            }
            if ($mark_eyi_reason) {
                unset($operation_items[$mark_eyi_key]);
            }

            unset($operation_items[$mark_cancel_key]);
            unset($operation_items[$mark_ceshi_key]);

            if ($mark_quhuo_reason) {
                array_push($operation_items, $mark_quhuo_reason);
            }
            if ($mark_eyi_reason) {
                array_push($operation_items, $mark_eyi_reason);
            }
            array_push($operation_items, $mark_ceshi_reason);
            array_push($operation_items, $mark_cancel_reason);
        }

        return $operation_items;
    }

    /**
     * 获取标记类型及其对应的支持
     */
    public function getMarkTypeAndReasons()
    {
        $operation_item_service = new COperationItemService();
        $operation_model = new OperationsModel();
        $types = $operation_model->getOperationsByKeys([ConstantConfig::ORDER_CANCEL_REASON_STR, ConstantConfig::ORDER_REJECT_REASON_STR]);
        $type_ids = [];
        foreach ($types as $type) {
            $type_ids[] = $type['id'];
        }

        $reasons = $operation_item_service->getOperationItemsByOperationIds($type_ids);


        //  将标记的原因进行排序
        $mark_cancel_reason = [];
        $mark_ceshi_reason = [];
        $mark_cancel_key=[];
        $mark_ceshi_key = [];
        $mark_quhuo_reason =[];
        $mark_quhuo_key = [];
        $mark_eyi_reason = [];
        $mark_eyi_key = [];

        foreach ($reasons as $key=>$value) {
            $reason = $value['item_name'];
            if($reason == '缺货取消') {
                $mark_quhuo_reason = $reasons[$key];
                $mark_quhuo_key = $key;
            } elseif ($reason == '恶意下单') {
                $mark_eyi_reason = $reasons[$key];
                $mark_eyi_key = $key;
            } elseif ( $reason == '取消标记') {
                array_push($mark_cancel_reason, $reasons[$key]);
                array_push($mark_cancel_key,$key);

            } elseif ($reason == '测试') {
                array_push($mark_ceshi_reason, $reasons[$key]);
                array_push($mark_ceshi_key,$key);

            }
        }

        list($cancel_key_1, $cancel_key_2) = $mark_cancel_key;
        list($ceshi_key_1, $ceshi_key_2) = $mark_ceshi_key;
        unset($reasons[$mark_quhuo_key]);
        unset($reasons[$mark_eyi_key]);
        unset($reasons[$cancel_key_1]);
        unset($reasons[$cancel_key_2]);
        unset($reasons[$ceshi_key_1]);
        unset($reasons[$ceshi_key_2]);

        array_push($reasons, $mark_quhuo_reason);
        array_push($reasons, $mark_eyi_reason);
        foreach ($mark_ceshi_reason as $ceshi_value) {
            array_push($reasons, $ceshi_value);
        }
        foreach ($mark_cancel_reason as $cancel_value) {
            array_push($reasons, $cancel_value);
        }

        return  ['types' => $types, 'reasons' => $reasons];
    }
    /**
     * 编辑订单签收/拒收后手机号可看时间范围
     * @param $operation_id
     * @param $operation_item_id
     * @param $telephone_limit_date
     * @return array
     * @throws \yii\db\Exception
     */
    public function editTelephoneLimitDate($operation_id, $operation_item_id, $telephone_limit_date)
    {
        if(empty($operation_id) || empty($operation_item_id)){
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        if(!is_numeric($telephone_limit_date) || intval($telephone_limit_date) < 0){
            return ['success' => false, 'msg' => '订单手机号可看时长必须为大于等于0的整数'];
        }
        //获取手机号可看时长信息
        $operation_model = new OperationsModel();
        $operation_info = $operation_model->getOperations(ConstantConfig::OPERATION_TELEPHONE_LIMIT_DATE);
        if(empty($operation_info) || (int)$operation_info['id'] != (int)$operation_id){
            return ['success' => false, 'msg' => '订单手机号可看时长信息不存在或已被删除'];
        }
        //获取详情信息
        $c_operation_item_service = new COperationItemService();
        $operation_item = $c_operation_item_service->getById($operation_item_id);
        if(empty($operation_item) || $operation_item['operation_id'] != (string)$operation_id){
            return ['success' => false, 'msg' => '订单手机号可看时长信息不存在或已被删除'];
        }
        if($operation_item['item_name'] == (string)$telephone_limit_date){
            return ['success' => true, 'msg' => '订单手机号可看时长修改成功'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            //修改订单手机号可看时间范围
            $operation_items_model = new OperationItemsModel();
            $operation_items_model->setId($operation_item_id);
            $operation_items_model->setItemName($telephone_limit_date);
            $operation_items_model->updateItemNameById();
            $transaction->commit();
            //写入redis
            RedisUtil::set(ConstantConfig::REDIS_TELEPHONE_LIMIT_DATE, $telephone_limit_date);
            return ['success' => true, 'msg' => '订单手机号可看时长修改成功'];
        }catch (Exception $e){
            $transaction->rollBack();
            return ['success' => false, 'msg' => '订单手机号可看时长修改失败'];
        }

    }


    /**
     * 编辑根据物流单号查询订单次数限制
     * @param $operation_id
     * @param $operation_item_id
     * @param $logistics_limit_times
     * @return array
     * @throws \yii\db\Exception
     */
    public function editLogisticsLimitTimes($operation_id, $operation_item_id, $logistics_limit_times)
    {
        if(empty($operation_id) || empty($operation_item_id)){
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        if(!is_numeric($logistics_limit_times) || intval($logistics_limit_times) < 0){
            return ['success' => false, 'msg' => '物流单号查询次数必须为大于等于0的整数'];
        }
        //获取物流单号查询次数信息
        $operation_model = new OperationsModel();
        $operation_info = $operation_model->getOperations(ConstantConfig::OPERATION_LOGISTICS_LIMIT_TIMES);
        if(empty($operation_info) || (int)$operation_info['id'] != (int)$operation_id){
            return ['success' => false, 'msg' => '物流单号查询订单次数信息不存在或已被删除'];
        }
        //获取详情信息
        $c_operation_item_service = new COperationItemService();
        $operation_item = $c_operation_item_service->getById($operation_item_id);
        if(empty($operation_item) || $operation_item['operation_id'] != (string)$operation_id){
            return ['success' => false, 'msg' => '物流单号查询订单次数不存在或已被删除'];
        }
        if($operation_item['item_name'] == (string)$logistics_limit_times){
            return ['success' => true, 'msg' => '物流单号查询订单次数修改成功'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            //修改物流单号查询订单次数
            $operation_items_model = new OperationItemsModel();
            $operation_items_model->setId($operation_item_id);
            $operation_items_model->setItemName($logistics_limit_times);
            $operation_items_model->updateItemNameById();
            $transaction->commit();

            //写入redis
            //设置失效时间为第二天凌晨00：00：00
            $next_time = strtotime(date('Y-m-d', strtotime('+1 day')));
            $expire_time = ($next_time - time());
            RedisUtil::set(ConstantConfig::REDIS_LOGISTICS_LIMIT_TIMES, $logistics_limit_times, null, $expire_time);
            return ['success' => true, 'msg' => '物流单号查询订单次数修改成功'];
        }catch (Exception $e){
            $transaction->rollBack();
            return ['success' => false, 'msg' => '物流单号查询订单次数修改失败'];
        }

    }


    /**
     * 获取外呼数据类型以及对应的外呼内容
     */

    public function getOuterCallTypeAndContents()
    {
        $operation_item_service = new COperationItemService();
        $operation_model = new OperationsModel();
        $types = $operation_model->getOperationsByKeys([ConstantConfig::OUTER_CALL_CONNECT, ConstantConfig::OUTER_CALL_DISCONNECT]);
        $type_ids = [];
        foreach ($types as $type) {
            $type_ids[] = $type['id'];
        }

        $reasons = $operation_item_service->getOperationItemsByOperationIds($type_ids);
        return  ['types' => $types, 'reasons' => $reasons];
    }

    /**
     * 获取退款原因
     * @param $operation_key
     * @return array
     */
    public function getAllOrderRefundReason($operation_key)
    {
        if (empty($operation_key)) {
            return [];
        }
        $operation_service = new COperationService();
        $operation_info = $operation_service->getOperationInfo($operation_key);
        if (empty($operation_info)) {
            return [];
        }

        $operation_id = $operation_info['id'];
        $operation_item_service = new COperationItemService();
        $operation_items = $operation_item_service->getOperationItemsByOperationId(intval($operation_id));
        $order_refund_reason = [];
        foreach ($operation_items as $refund_detail){
            $order_refund_reason[$refund_detail['item_key']] = $refund_detail['item_name'];
        }
        return array_reverse($order_refund_reason);
    }
} 