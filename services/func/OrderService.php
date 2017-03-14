<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 7/31/15
 * Time: 10:24 AM
 *
 * PHP version 5
 */

namespace app\services\func;

use app\components\UserIdentity;
use app\models\OrderDetailsModel;
use app\models\OrderInfoModel;
use app\models\OrderPaymentDetailsModel;
use app\services\order\COrderService;
use app\util\ArrayUtil;
use app\util\ConstantConfig;
use app\util\ExcelUtil;
use app\util\StringUtil;
use Exception;
use Yii;
use yii\helpers\ArrayHelper;

class OrderService
{
    /**
     * 搜索订单资源信息
     * @param $params
     * @param $ordinal_str
     * @param $ordinal_type
     * @param int $limit
     * @param int $limit_size
     * @return array
     */
    public function searchOrderList($params, $ordinal_str, $ordinal_type, $limit = 0, $limit_size = 20)
    {
        //db查询
        $order_info_model = new OrderInfoModel();
        $count = $order_info_model->countOrderList($params);
        $order_list = $order_info_model->searchOrderList($params, $limit, $limit_size, $ordinal_str, $ordinal_type);
        //格式化订单数据
        $order_list = $this->_formatOrderList($order_list);
        return ['success' => true, 'count' => $count, 'order_data' => $order_list];
    }
    /**
     * 格式化订单列表数据
     * @param $order_data
     * @return mixed
     */
    private function _formatOrderList($order_data)
    {
        if (empty($order_data)) {
            return [];
        }
        $order_status_arr = ConstantConfig::orderStatusArray();
        $pay_status_arr = ConstantConfig::payStatusArray();
        foreach ($order_data as $key => &$value) {
            // 增加地址的截取字符串
            $order_status = intval($value['order_status']);
            $pay_status = intval($value['pay_status']);
            $value['order_status_str'] = array_key_exists($order_status, $order_status_arr) ? $order_status_arr[$order_status] : '未知';
            $value['pay_status_str'] = array_key_exists($pay_status, $pay_status_arr) ? $pay_status_arr[$pay_status] : '未知';
        }
        return $order_data;
    }

    /**
     * 生成订单单号，获取规则： 获取时间的 ymdHis + 3位毫秒数+ 4位随机数
     * @return string
     */
    public function generateOrderSN()
    {
        $cur_data = date("YmdHis");
        $m_list = explode(' ', microtime());
        $um = $m_list[0];//微秒
        $ms = intval($um * 1000);
        $ms_str = sprintf("%03d", $ms);
        $rand_num = mt_rand(1000, 9999);
        return $cur_data . $ms_str . $rand_num;
    }


    /**
     * 获取订单信息
     * @param $order_id
     * @return array|null
     */
    public function getOrderInfo($order_id)
    {
        if (empty($order_id)) {
            return null;
        }
        //订单信息
        $order_info_model = new OrderInfoModel();
        $order_info_model->setId($order_id);
        $order_info = $order_info_model->getOrderInfo();
        if (empty($order_info)) {
            return null;
        }
        //获取订单状态
        $order_status_arr = ConstantConfig::orderStatusArray();
        //获取付款状态
        $pay_status_arr = ConstantConfig::payStatusArray();
        //获取支付方式
        $pay_type_arr = ConstantConfig::payTypeArr();
        // 支付途径
        $pay_mode_arr = ConstantConfig::PayModeArr();
        $order_status = $order_info['order_status'];
        $pay_status = $order_info['pay_status'];
        $order_info['order_status_str'] = array_key_exists($order_status, $order_status_arr) ? $order_status_arr[$order_status] : '未知';
        $order_info['pay_status_str'] = array_key_exists($pay_status, $pay_status_arr) ? $pay_status_arr[$pay_status] : '未知';
        //获取订单明细
        $order_details_model = new OrderDetailsModel();
        $order_details = $order_details_model->getOrderDetails($order_id);
        //获取支付明细
        $order_payment_details_model = new OrderPaymentDetailsModel();
        $payment_details_info = $order_payment_details_model->getOrderPaymentDetails($order_id);
        foreach ($payment_details_info as &$value) {
            $pay_type = $value['pay_type'];
            $pay_mode = $value['pay_mode'];
            $value['pay_type_str'] = array_key_exists($pay_type, $pay_type_arr) ? $pay_type_arr[$pay_type] : '未知';
            $value['pay_mode_str'] = array_key_exists($pay_mode, $pay_mode_arr) ? $pay_mode_arr[$pay_mode] : '未知';
        }
        return ['order_info' => $order_info, 'order_details' => $order_details, 'payment_details' => $payment_details_info];
    }

    /**
     * 订单客审与反审
     * @param $order_ids
     * @param $action_type
     * @param $audit_user_id
     * @param $audit_user_name
     * @return array
     */
    public function toOrCancelExampleOrder($order_ids, $action_type, $audit_user_id, $audit_user_name)
    {
        //检查订单是否需要审核，状态是否正确
        $validate_for_example = $this->validateOrderStatusCanToExample($order_ids, $action_type);
        if (empty($validate_for_example) || empty($validate_for_example['y'])) {
            //错误信息
            $error_str = '没有订单能够进行处理';
            if (isset($validate_for_example['n'])) {
                $n_orders = $validate_for_example['n'];
                if (!empty($n_orders)) {
                    $error_msg = [];
                    foreach ($n_orders as $v) {
                        if (!array_key_exists($v['msg'], $error_msg)) {
                            $error_msg[$v['msg']] = [];
                        }
                        array_push($error_msg[$v['msg']], $v['sn']);
                    }
                    $error_str .= '，';
                    foreach ($error_msg as $msg => $sns) {
                        $error_str .= implode('、', $sns) . '：' . $msg;
                    }
                }
            }

            return ['success' => false, 'msg' => $error_str];
        }
        $y_order_ids = $validate_for_example['y'];//客审/反审订单id数组
        $n_orders = $validate_for_example['n'];//不能进行客审/反审订单sn及错误信息数组
        $action_relation_order_status = ConstantConfig::confirmationRelationOrderStatus()[$action_type];
        if($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE) {
            $action_str = '客审';
        } else {
            $action_str = '反审';
        }
        $order_info_model = new OrderInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            // 更改订单状态
            $res = $order_info_model->updateOrderStatusAndInfo(
                $y_order_ids, $action_relation_order_status,
                $audit_user_id, $audit_user_name
            );
            if (!$res) {
                throw new Exception('更改订单状态错误：' . $action_str . '失败');
            }
            $error_str = '';
            if (!empty($n_orders)) {
                $error_msg = [];
                foreach ($n_orders as $v) {
                    if (!array_key_exists($v['msg'], $error_msg)) {
                        $error_msg[$v['msg']] = [];
                    }
                    array_push($error_msg[$v['msg']], $v['sn']);
                }
                $error_str = '，下列订单无法进行' . $action_str . ' ';
                foreach ($error_msg as $msg => $sns) {
                    $error_str .= implode('、', $sns) . '：' . $msg;
                }
            }

            $transaction->commit();

            return ['success' => true, 'msg' => $action_str . '成功' . $error_str, 'ids' => $y_order_ids];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => $action_str . '失败：' . $e->getMessage()];
        }

    }

    /**
     * 订单作废操作
     * @param $order_ids
     * @param $user_info
     * @param $cancel_type
     * @param $cancel_reason
     * @return array
     */
    public function orderCancel($order_ids, $user_info, $cancel_type, $cancel_reason)
    {
        $c_order_service = new COrderService();
        $res = $c_order_service->validateOrderCancel($order_ids);
        if (empty($res) || empty($res['y'])) {
            //错误信息
            $error_str = '没有订单能够进行处理';
            if (isset($res['n'])) {
                $n_orders = $res['n'];
                if (!empty($n_orders)) {
                    $error_msg = [];
                    foreach ($n_orders as $v) {
                        if (!array_key_exists($v['msg'], $error_msg)) {
                            $error_msg[$v['msg']] = [];
                        }
                        array_push($error_msg[$v['msg']], $v['sn']);
                    }
                    $error_str .= '，';
                    foreach ($error_msg as $msg => $sns) {
                        $error_str .= implode('、', $sns) . '：' . $msg;
                    }
                }
            }

            return ['success' => false, 'msg' => $error_str];
        }
        $y_order_ids = $res['y'];
        $n_orders = $res['n'];

        $order_info_model = new OrderInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();

        try {

            $res = $order_info_model->updateOrderStatus($y_order_ids, ConstantConfig::ORDER_STATUS_CANCEL, 0, '', -1,
                ConstantConfig::FLAG_RED, $cancel_type, $cancel_reason);
            if (!$res) {
                throw new Exception('更改订单状态错误：订单作废失败');
            }
            //维护库存可用减未审数
            $sync_params = $this->_getOrderMerchandiseNumbers($y_order_ids);
            foreach ($sync_params as &$s_p) {
                $s_p['rest_numbers'] = $s_p['numbers'];
            }
            if (!empty($sync_params)) {
                $gearman_client_utils = new GearmanClientUtils();
                $r_res = $gearman_client_utils->syncInventoryForRestNumbers($sync_params);
                if (!empty($r_res)) {
                    if (!$r_res['success']) {
                        throw new Exception($r_res['msg']);
                    }

                    // 商品可用减未审数的更改
                    $new_inventory_data = $sync_params;
                    $new_order_store_dict = [];
                    $new_order_data_dict = [];
                    foreach($new_inventory_data as $new_item) {
                        $cur_new_order_id = $new_item['order_id'];
                        $cur_new_tore_id = $new_item['store_id'];
                        $new_order_store_dict[$cur_new_order_id] = $cur_new_tore_id;
                        if (!array_key_exists($cur_new_order_id, $new_order_data_dict)) {
                            $new_order_data_dict[$cur_new_order_id] = [];
                        }
                        $new_item['merchandise_specification_code'] = $new_item['specification_code'];
                        unset($new_item['specification_code']);
                        unset($new_item['numbers']);
                        unset($new_item['store_id']);
                        unset($new_item['order_id']);
                        $new_order_data_dict[$cur_new_order_id][] = $new_item;
                    }

                    foreach ($new_order_data_dict as $new_order_id=>$new_data_item) {
                        $new_store_id = $new_order_store_dict[$new_order_id];
                        $inventory_work = new InventoryWorker();
                        $inventory_work->inventoryProcess([
                            'action' => 'cancel',
                            'action_type' => 'execute',
                            'resource_type' => ConstantConfig::INVENTORY_RESOURCE_ORDER,
                            'resource_id' => $new_order_id,
                            'project_type' => CStoreService::getProjectType($new_store_id),
                            'data' => $new_data_item
                        ]);
                    }
                }else{
                    throw new Exception('商品可用减数更改失败，未获取到返回参数');
                }
            }

            $corder_service = new COrderService();
            $gearman_client_utils = new GearmanClientUtils();
            $orders = $corder_service->getOrderInfoWithDetails($y_order_ids);

            //同步订单信息至进销存系统 （用户手动取消订单，在进销存中有数据）
            $sync_orders = [];
            foreach ($orders as $item) {
                $sync_orders[] = $item['order_info'];
            }
            if (!empty($sync_orders)) {
                $sync_res = $gearman_client_utils->UpdateOrderInfo2Psi($sync_orders);
                if (!$sync_res['success']) {
                    throw new Exception($sync_res['msg']);
                }
            }

            //订单操作记录
            $corder_service = new COrderService();
            $action_res = $corder_service->orderActionLogs($y_order_ids, $user_info['id'], $user_info['name']);
            if (!$action_res) {
                throw new Exception('订单操作记录日志错误');
            }

            $error_str = '';
            if (!empty($n_orders)) {
                $error_msg = [];
                foreach ($n_orders as $v) {
                    if (!array_key_exists($v['msg'], $error_msg)) {
                        $error_msg[$v['msg']] = [];
                    }
                    array_push($error_msg[$v['msg']], $v['sn']);
                }
                $error_str = '，下列订单无法进行作废 ';
                foreach ($error_msg as $msg => $sns) {
                    $error_str .= implode('、', $sns) . '：' . $msg;
                }
            }

            $transaction->commit();


            return ['success' => true, 'msg' => '操作成功 ' . $error_str, 'ids' => $y_order_ids];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '订单作废失败：' . $e->getMessage()];
        }
    }

    /**
     * 订单反作废操作
     * @param $order_ids
     * @param $user_info
     * @return array|int
     */
    public function orderAntiCancel($order_ids, $user_info)
    {
        $c_order_service = new COrderService();
        $res = $c_order_service->orderAntiCancel($order_ids);
        if (empty($res) || (empty($res['yd']) and empty($res['yc']))) {
            return ['success' => false, 'msg' => '只有交易取消状态的订单才能反作废'];
        }
        $y_d_order_ids = $res['yd']; //修改为待付款状态的订单
        $y_c_order_ids = $res['yc']; //修改为待确认状态的订单
        $order_info_model = new OrderInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            //修改付款状态为待付款
            $y_order_ids = array_merge($y_c_order_ids, $y_d_order_ids);
            $pay_status_res = $order_info_model->updateOrderInfoByAntiCancelOrder($y_order_ids,
                ConstantConfig::PAY_STATUS_UNPAID, ConstantConfig::MERGE_FALSE, ConstantConfig::SEPARATE_FALSE);
            if (!$pay_status_res) {
                throw new Exception("修改付款状态失败");
            }
            //修改订单状态为待付款
            if (!empty($y_d_order_ids)) {
                $d_res = $order_info_model->updateOrderStatus($y_d_order_ids, ConstantConfig::ORDER_STATUS_DEFAULT);
                if (!$d_res) {
                    throw new Exception("修改订单状态失败");
                }

                $order_info_model->updateOrderUnpaidPrice($y_d_order_ids);
                if (!$d_res) {
                    throw new Exception("修改订单金额失败");
                }
            }

            //修改订单状态为待确认
            if (!empty($y_c_order_ids)) {
                $c_res = $order_info_model->updateOrderStatus($y_c_order_ids, ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION);
                if (!$c_res) {
                    throw new Exception("修改订单状态失败");
                }
            }

            //维护库存可用减未审数
            $sync_params = $this->_getOrderMerchandiseNumbers($y_order_ids);
            if(!empty($sync_params)) {
                foreach($sync_params as &$item){
                    $temp_numbers = $item['numbers'];
//                    $item['numbers'] = -(int)$temp_numbers;
                    $item['rest_numbers'] = -(int)$temp_numbers;
                }
                $gearman_client_utils = new GearmanClientUtils();
                $r_res = $gearman_client_utils->syncInventoryForRestNumbers($sync_params);
                if (!empty($r_res)) {
                    if (!$r_res['success']) {
                        throw new Exception($r_res['msg']);
                    }

                    // 商品可用减未审数的更改
                    $new_inventory_data = $sync_params;
                    $new_order_store_dict = [];
                    $new_order_data_dict = [];
                    foreach($new_inventory_data as $new_item) {
                        $cur_new_order_id = $new_item['order_id'];
                        $cur_new_tore_id = $new_item['store_id'];
                        $new_order_store_dict[$cur_new_order_id] = $cur_new_tore_id;
                        if (!array_key_exists($cur_new_order_id, $new_order_data_dict)) {
                            $new_order_data_dict[$cur_new_order_id] = [];
                        }
                        $new_item['merchandise_specification_code'] = $new_item['specification_code'];
                        unset($new_item['specification_code']);
                        unset($new_item['numbers']);
                        unset($new_item['store_id']);
                        unset($new_item['order_id']);
                        unset($new_item['inventory_state']);
                        $new_order_data_dict[$cur_new_order_id][] = $new_item;
                    }

                    foreach ($new_order_data_dict as $new_order_id=>$new_data_item) {
                        $new_store_id = $new_order_store_dict[$new_order_id];
                        $inventory_work = new InventoryWorker();
                        $inventory_work->inventoryProcess([
                            'action' => 'anti_cancel',
                            'action_type' => 'execute',
                            'resource_type' => ConstantConfig::INVENTORY_RESOURCE_ORDER,
                            'resource_id' => $new_order_id,
                            'project_type' => CStoreService::getProjectType($new_store_id),
                            'data' => $new_data_item
                        ]);
                    }
                }else{
                    throw new Exception('商品可用减数更改失败，未获取到返回参数');
                }
            }

            $corder_service = new COrderService();

            //订单操作记录
            $action_res = $corder_service->orderActionLogs($y_order_ids, $user_info['id'], $user_info['name']);
            if (!$action_res) {
                throw new Exception('订单操作记录日志错误');
            }

            $transaction->commit();

            return ['success' => true, 'msg' => '操作成功', 'ids' => $y_order_ids, 'sysnc_params' => $sync_params];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '订单反作废失败：' . $e->getMessage()];
        }
    }

    /**
     * 取消待付款订单，规则：在线支付订单提交3天后未支付
     * @return array
     * @throws \yii\db\Exception
     */
    public function cancelUnpaidOrder()
    {
        $order_info_model = new OrderInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $limit_pay_day = SiteConfig::get('limit_pay_day');
            if (empty($limit_pay_day)) {
                return ['success' => false, 'msg' => '未检索到限制时间'];
            }
            $limit_time = $limit_pay_day * 24 * 60 * 60;
            //检索出需取消的订单
            $order_info_model->setPayType(ConstantConfig::PAY_TYPE_ONLINE);
            $order_info_model->setPayStatus(ConstantConfig::PAY_STATUS_UNPAID);
            $order_info_model->setOrderStatus(ConstantConfig::ORDER_STATUS_DEFAULT);
            $current_time = Yii::$app->params['current_time'];
            $created_limit_time = $current_time - $limit_time;
            $need_cancel_orders = $order_info_model->getOnlineUnpaidOrder($created_limit_time);
            if (empty($need_cancel_orders)) {
                return ['success' => true, 'msg' => '暂无待取消订单'];
            }
            $order_ids = [];
            foreach ($need_cancel_orders as $order) {
                array_push($order_ids, $order['id']);
            }

            //取消订单
            $res = $order_info_model->updateOrderStatus($order_ids, ConstantConfig::ORDER_STATUS_CANCEL);
            if (!$res) {
                throw new Exception('更改订单状态错误：订单作废失败');
            }

            //维护库存可用减未审数
            $merchandise_list = $this->_getOrderMerchandiseNumbers($order_ids);
            $sync_params = [];
            $new_inventory_dict = [];
            if(!empty($merchandise_list)){
                foreach($merchandise_list as $merchandise){
                    $new_inventory_data = $merchandise;
                    $merchandise['rest_numbers'] = (int)$merchandise['numbers'];
                    unset($merchandise['store_id']);
                    $sync_params[] = $merchandise;

                    //库存服务对接
                    if ($merchandise['inventory_state'] == ConstantConfig::ORDER_DETAIL_INVENTORY_STATE_DEFAULT) {
                        if (!array_key_exists($merchandise['order_id'], $new_inventory_dict)) {
                            $new_inventory_dict[$merchandise['order_id']] = [];
                        }
                        $new_inventory_data['rest_numbers'] = (int)$merchandise['numbers'];
                        $new_inventory_data['merchandise_specification_code'] = $new_inventory_data['specification_code'];
                        unset($new_inventory_data['specification_code']);
                        unset($new_inventory_data['numbers']);
                        $new_inventory_dict[$merchandise['order_id']][] = $new_inventory_data;
                    }
                }
            }
            if (!empty($sync_params)) {
                $gearman_client_utils = new GearmanClientUtils();
                $r_res = $gearman_client_utils->syncInventoryForRestNumbers($sync_params);
                if (!empty($r_res)) {
                    if (!$r_res['success']) {
                        throw new Exception($r_res['msg']);
                    }

                    if (!empty($new_inventory_dict)) {
                        foreach ($new_inventory_dict as $new_order_id => $new_order_data_item) {
                            $c_store_id =  $new_order_data_item['store_id'];
                            $inventory_work = new InventoryWorker();
                            $inventory_work->inventoryProcess([
                                'action' => 'cancel',
                                'action_type' => 'execute',
                                'resource_type' => ConstantConfig::INVENTORY_RESOURCE_ORDER,
                                'resource_id' => $new_order_id,
                                'project_type' => CStoreService::getProjectType($c_store_id),
                                'data' => $new_order_data_item
                            ]);
                        }
                    }
                }else{
                    throw new Exception('商品可用减数更改失败，未获取到返回参数');
                }
            }

            //订单操作记录
            $c_order_service = new COrderService();
            $action_res = $c_order_service->orderActionLogs($order_ids);
            if (!$action_res) {
                throw new Exception('订单操作记录日志错误');
            }

            $transaction->commit();
            return ['success' => true, 'msg' => '操作成功'];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '订单作废失败：' . $e->getMessage()];
        }
    }

    
    /**
     * 申请退款
     * @param $order_ids
     * @param $user_info
     * @return array
     */
    public function applyRefund($order_ids, $user_info)
    {
        //检测订单能否申请退款
        $corder_service = new COrderService();
        $validate_res = $corder_service->validateApplyRefund($order_ids);
        if (empty($validate_res) || empty($validate_res['y'])) {
            return ['success' => false, 'msg' => '系统只支持待确认状态的订单进行申请退款，请反审后再操作'];
        }
        $y_order_ids = $validate_res['y'];
        $n_order_ids = $validate_res['n'];

        $order_info_model = new OrderInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $res = $order_info_model->updatePayStatus($y_order_ids, ConstantConfig::PAY_STATUS_REFUNDING);
            if (!$res) {
                throw new Exception('订单退款失败');
            }

            //订单操作记录
            $c_order_service = new COrderService();
            $action_res = $c_order_service->orderActionLogs($y_order_ids, $user_info['id'], $user_info['name']);
            if (!$action_res) {
                throw new Exception('订单操作记录日志错误');
            }

            //后端作业 指定天数（7天）后自动完成退款
            //            $order_client = new OrderClient();
            //            $order_client->automateCompleteRefund($y_order_ids);

            $transaction->commit();

            //状态有误订单
            $error_str = '';
            if (!empty($n_order_ids)) {
                $error_ids = join(',', $n_order_ids);
                $error_str = '，申请退款只支持待确认订单，下列订单状态错误：' . $error_ids;
            }

            //同步订单信息至PPC
            $gearman_client_utils = new GearmanClientUtils();
            $orders = $c_order_service->getOrderInfoWithDetails($y_order_ids);
            $gearman_client_utils->syncOrderInfo2PPC($orders);
            // 筛出订单信息，进行同步
            $need_sync_orders = array_map(function ($order) {
                return $order['order_info'];
            }, $orders);
            $gearman_client_utils->UpdateOrderInfo2Psi($need_sync_orders);
            // 申请退款时，需要同步推送至EC
            $gearman_client_utils->syncOrderLogisticsPush($orders);

            return ['success' => true, 'msg' => '操作成功' . $error_str, 'ids' => $y_order_ids,
                'pay_status_str' => ConstantConfig::PAY_STATUS_STR_REFUNDING];
        } catch (Exception $e) {
            $transaction->rollBack();
            $connection->close();
            return ['success' => false, 'msg' => '申请退款失败：' . $e->getMessage()];
        }
    }
    /**
     * 导出订单
     * @param $query
     * @param $ordinal_str
     * @param $ordinal_type
     * @return array
     */
    public function exportData($query, $ordinal_str, $ordinal_type)
    {
        //获取订单单信息
        $order_info_model = new OrderInfoModel();
        $order_info_list = $order_info_model->searchOrderList($query, $ordinal_str, $ordinal_type);
        if (empty($order_info_list)) {
            return ['success' => false, 'msg' => "无可导出的订单"];
        }

        $order_ids = [];
        $order_info_dict = [];
        foreach ($order_info_list as $item) {
            $order_ids[] = (int)$item['id'];
            $order_info_dict[(int)$item['id']] = $item;
        }
        //获取订单详情
        $order_detail_model = new OrderDetailsModel();
        $details = $order_detail_model->getOrderDetailByIds($order_ids);
        if (empty($details)) {
            return ["success" => false, "msg" => "请选择需要导出的采购订单"];
        }

        // 订单状态数组
        $order_status_arr = ConstantConfig::orderStatusArray();
        //支付状态数组
        $pay_status_arr = ConstantConfig::payStatusArray();

        $return_data = [];
        foreach ($details as $detail) {
            $order_info = $order_info_dict[(int)$detail['order_id']];
            $row_data = [];
            // 预定时间
            $row_data['created_at'] = $order_info['created_at'];
            // 订单号
            $row_data['sn'] = $order_info['sn'];
            //经销商
            $row_data['distributor_name'] = $order_info['distributor_name'];
            //景区名称
            $row_data['scenic_name'] = $order_info['scenic_name'];
            //订单状态
            $row_data['order_status'] =
                array_key_exists(intval($order_info['order_status']), $order_status_arr)
                    ? $order_status_arr[intval($order_info['order_status'])] : '未知';
            //支付状态
            $row_data['pay_status'] =
                array_key_exists(intval($order_info['pay_status']), $pay_status_arr)
                    ? $pay_status_arr[intval($order_info['pay_status'])] : '未知';
            //游客姓名
            $row_data['tourist_name'] = $order_info['tourist_name'];
            //手机号码
            $row_data['mobile'] = $order_info['mobile'];
            //应付金额
            $row_data['pay_price'] = $order_info['pay_price'];
            //客审人
            $row_data['audit_user_name'] = $order_info['audit_user_name'];
            //门票名称
            $row_data['ticket_name'] = $detail['ticket_name'];
            //门票金额
            $row_data['ticket_price'] = $detail['ticket_price'];
            //门票数量
            $row_data['ticket_numbers'] = $detail['ticket_numbers'];
            //门票总额
            $row_data['ticket_amount'] = $detail['ticket_amount'];
            //备注
            $row_data['remark'] = $order_info['remark'];
            array_push($return_data, $row_data);
        }

        return ["success" => true, "data" => $return_data, "ids" => $order_ids];
    }
    /**
     * 判断订单是否能够进行客审或反审
     * @param $order_ids
     * @param $action_type
     * @return array
     */
    public function validateOrderStatusCanToExample($order_ids, $action_type)
    {
        if (!in_array($action_type, ConstantConfig::confirmationActionType())){
            return [];
        }

        $c_order_service = new COrderService();
        $order_info_list = $c_order_service->getOrderInfoWithDetails($order_ids);

        if(empty($order_info_list)){
            return [];
        }

        $y_array = [];//能进行客审或反审的订单
        $n_array = [];//不能进行客审的订单数组[$order_sn=>$error_msg]
        foreach($order_info_list as $item){
            $order_info = $item['order_info'];
            $order_id = $order_info['id'];
            $order_sn = $order_info['sn']; // 订单号
            $order_status = $order_info['order_status']; //订单状态
            $pay_status = $order_info['pay_status']; //付款状态
            $paid_price = floatval($order_info['paid_price']); //已付金额
            $pay_price = floatval($order_info['pay_price']); //应付金额
            if($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE &&
                $order_status == ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION){//客审 等待确认
                //已付金额
                if($paid_price > $pay_price){
                    array_push($n_array, ['sn' => $order_sn, 'msg' => '已支付金额不能大于应付金额']);
                    continue;
                }
                //付款状态
                if($pay_status==ConstantConfig::PAY_STATUS_REFUNDING){
                    array_push($n_array, ['sn'=>$order_sn, 'msg'=>'退款中订单不能进行客审']);
                    continue;
                }
                //订单明细
                $order_details = $item['details'];
                if(empty($order_details)){
                    array_push($n_array, ['sn'=>$order_sn, 'msg'=>'无商品信息']);
                    continue;
                }
                array_push($y_array, $order_id);

            }elseif($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE){
                array_push($y_array, $order_id);
            }else{
                array_push($n_array, ['sn'=>$order_sn, 'msg'=>'订单状态有误']);
            }
        }
        return ['y'=>$y_array, 'n'=>$n_array];
    }

    /**
     * 退款单审核
     * @param $service_ids
     * @param $user_info
     * @return array
     */
    public function refundAudit($service_ids, $user_info)
    {
        if (empty($service_ids) || empty($user_info)) {
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        //获取订单明细
        $order_info_model = new OrderInfoModel();
        $order_info = $order_info_model->getOrderInfoList($service_ids);
        if (empty($order_info)) {
            return ['success' => false, 'msg' => '订单信息不存在或已删除'];
        }
        //状态确认
        $need_process_ids = [];
        $state_error_data = [];
        foreach ($order_info as $order_item) {
            $pay_status = $order_item['pay_status'];
            $id = $order_item['id'];
            $order_sn = $order_item['sn'];
            if ($pay_status == ConstantConfig::PAY_STATUS_REFUNDING) {
                $need_process_ids[] = $id;
            } else {
                $state_error_data[] = $order_sn;
            }
        }
        if (empty($need_process_ids)) {
            return ['success' => false, 'msg' => "订单付款状态有误,无数据处理", 'error_data' => $state_error_data];
        }

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $res = $order_info_model->updateRefundStatus($need_process_ids, ConstantConfig::PAY_STATUS_REFUNDED);
            if (!$res) {
                throw new Exception('更新订单付款状态失败');
            }
            $transaction->commit();
            return ["success" => true, "msg" => "退款审核操作成功", "ids" => $need_process_ids, "error_data" => $state_error_data];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, "msg" => "退款审核操作失败:" . $e->getMessage(), 'error_data' => $state_error_data,'ids'=>$need_process_ids];
        }

    }
}