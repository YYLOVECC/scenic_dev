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

use app\models\OrderDetailsModel;
use app\models\OrderInfoModel;
use app\models\OrderPaymentDetailsModel;
use app\models\ScenicModel;
use app\models\TicketModel;
use app\models\UserInfoModel;
use app\services\order\COrderService;
use app\util\ConstantConfig;
use Exception;
use Yii;

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
            $current_time = time();
            $value['admission_status'] = $value['admission_time'] - $current_time < 0 ? '已入园' : '未入园';
        }
        return $order_data;
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
        $update_order_ids = $validate_for_example['ids'];
        $action_relation_order_status = ConstantConfig::confirmationRelationOrderStatus()[$action_type];
        if ($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE) {
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
            //更改景区状态
            $scenic_model = new ScenicModel();
            $update_res = $scenic_model->updateStatus($action_type, $update_order_ids);
            if ($update_res) {
                throw new Exception('更改门票信息失败');
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
        if (!in_array($action_type, ConstantConfig::confirmationActionType())) {
            return [];
        }

        $c_order_service = new COrderService();
        $order_info_list = $c_order_service->getOrderInfoWithDetails($order_ids);

        if (empty($order_info_list)) {
            return [];
        }

        $y_array = [];//能进行客审或反审的订单
        $id_array = [];//修改景区表状态
        $n_array = [];//不能进行客审的订单数组[$order_sn=>$error_msg]
        foreach ($order_info_list as $item) {
            $order_info = $item['order_info'];
            $order_id = $order_info['id'];
            $order_sn = $order_info['sn']; // 订单号
            $order_status = $order_info['order_status']; //订单状态
            $pay_status = $order_info['pay_status']; //付款状态
            $paid_price = floatval($order_info['paid_price']); //已付金额
            $pay_price = floatval($order_info['pay_price']); //应付金额
            $order_type = $order_info['order_type']; //订单类型
            if ($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE &&
                $order_status == ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION
            ) {//客审 等待确认
                //已付金额
                if ($paid_price > $pay_price) {
                    array_push($n_array, ['sn' => $order_sn, 'msg' => '已支付金额不能大于应付金额']);
                    continue;
                }
                //付款状态
                if ($pay_status == ConstantConfig::PAY_STATUS_REFUNDING) {
                    array_push($n_array, ['sn' => $order_sn, 'msg' => '退款中订单不能进行客审']);
                    continue;
                }
                //订单明细
                $order_details = $item['details'];
                if (empty($order_details)) {
                    array_push($n_array, ['sn' => $order_sn, 'msg' => '无商品信息']);
                    continue;
                }
                if ($order_type == 2) {
                    array_push($id_array, $order_id);
                }
                array_push($y_array, $order_id);

            } elseif ($action_type == ConstantConfig::CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE
                and $order_status == ConstantConfig::ORDER_STATUS_COMPLETE
            ) {
                if ($order_type == 2) {
                    array_push($id_array, $order_id);
                }
                array_push($y_array, $order_id);
            } else {
                array_push($n_array, ['sn' => $order_sn, 'msg' => '订单状态有误']);
            }
        }
        return ['y' => $y_array, 'n' => $n_array, 'ids' => $id_array];
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
        //获取订单表信息
        $order_info_model = new OrderInfoModel();
        $order_info = $order_info_model->getOrderInfoList($service_ids);
        if (empty($order_info)) {
            return ['success' => false, 'msg' => '订单信息不存在或已删除'];
        }
        //状态确认
        $need_process_ids = [];
        $state_error_data = [];
        //
        $valid_user_id = [];//需要退款的用户
        $current_time = time();
        foreach ($order_info as $order_item) {
            $pay_status = $order_item['pay_status'];
            $id = $order_item['id'];
            $order_sn = $order_item['sn'];
            $user_id = $order_item['user_id'];
            $paid_price = $order_item['paid_price'];
            $admission_time = $order_item['admission_time'];
            if ($pay_status == ConstantConfig::PAY_STATUS_REFUNDING && $admission_time - $current_time > 0) {
                $need_process_ids[] = $id;
                $valid_user_id[$user_id] = $paid_price;
            } else {
                $state_error_data[] = $order_sn;
            }
        }
        if (empty($need_process_ids)) {
            return ['success' => false, 'msg' => "订单付款状态有误或已入园,无数据处理", 'error_data' => $state_error_data];
        }
        $scenic_id_arr = [];//需要删除景区进程
        $valid_order_id = [];
        foreach ($order_info as $item) {
            $order_type = $item['order_type'];
            $scenic_id = $item['scenic_id'];
            $user_id = $item['user_id'];
            $order_id = $item['id'];
            if ($order_type == 2) {
                $scenic_id_arr[$user_id] = $scenic_id;
                $valid_order_id[] = $order_id;
            }
        }
        //获取订单明细
        $order_details_model = new OrderDetailsModel();
        $details = $order_details_model->getOrderDetailByIds($valid_order_id);
        $valid_details = [];
        if (!empty($details)) {
            foreach ($details as $detail_item) {
//                $numbers = $detail_item['ticket_numbers'];
                $valid_details[$detail_item['scenic_id']] = $detail_item;
            }
        }
        $scenic_model = new ScenicModel();
        $all_scenic_info = [];
        foreach ($scenic_id_arr as $user_id => $scenic_id) {
            $scenic_info = $scenic_model->getScenicInfo($user_id, $scenic_id);
            $all_scenic_info[] = $scenic_info;
        }
        //删除景区id;
        $delete_scenic_ids = [];
        //门票
        $valid_scenic_ids = [];
        foreach ($all_scenic_info as $item) {
            $status = $item['status'];
            $scenic_id = $item['id'];
            if ($status == 3) {
                $delete_scenic_ids[] = $scenic_id;
            } else {
                $valid_scenic_ids[] = $scenic_id;
            }
        }
        $ticket_model = new TicketModel();
        $update_ticket_ids = [];
        if (!empty($valid_scenic_ids)) {
            //查找此景区的门票信息
            $ticket_info = $ticket_model->getTicketInfo($valid_scenic_ids);
            foreach ($ticket_info as $ticket) {
                $scenic_id = $ticket['scenic_id'];
                $parent_id = $ticket['parent_id'];
                $ticket_id = $ticket['id'];
                if (!empty($valid_details) and array_key_exists($scenic_id, $valid_details)) {
                    $detail_item = $valid_details[$scenic_id];
                    foreach ($detail_item as $value) {
                        $parent_ticket_id = $value['ticket_id'];
                        $numbers = $value['ticket_numbers'];
                        if ($parent_ticket_id == $parent_id) {
                            $update_ticket_ids[$ticket_id] = $numbers;
                        }
                    }
                }
            }
        }
        $user_info_model = new UserInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $res = $order_info_model->updateOrderAndPayStatus(
                $need_process_ids,
                ConstantConfig::ORDER_STATUS_CANCEL, ConstantConfig::PAY_STATUS_REFUNDED);
            if (!$res) {
                throw new Exception('更新订单退款状态和订单状态失败');
            }
            foreach ($valid_user_id as $user_id => $paid_price) {
                //更新用户金额
                $user_info_model->updatePrice($user_id, $paid_price);
            }
            if (!empty($delete_scenic_ids)) {
                $scenic_res = $scenic_model->deleteScenicInfo($delete_scenic_ids);
                if (!$scenic_res) {
                    throw new Exception('删除景区信息失败');
                }
            }
            foreach ($valid_user_id as $user_id => $paid_price) {
                //更新用户金额
                $user_info_model->updatePrice($user_id, $paid_price);
            }
            if (!empty($update_ticket_ids)) {
                foreach ($update_ticket_ids as $ticket_id => $number) {
                    $ticket_model->updateTicketInfo($ticket_id, $number);
                }
            }
            $transaction->commit();
            return ["success" => true, "msg" => "退款审核操作成功", "ids" => $need_process_ids, "error_data" => $state_error_data];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, "msg" => "退款审核操作失败:" . $e->getMessage(), 'error_data' => $state_error_data, 'ids' => $need_process_ids];
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
            $pay_time = 3; //三小时未支付取消订单
            $limit_time =  $pay_time * 60 * 60;
//            $limit_time =   1 * 60;

            //检索出需取消的订单
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
            $transaction->commit();
            return ['success' => true, 'msg' => '操作成功'];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '订单作废失败：' . $e->getMessage()];
        }
    }

    /**
     * 完成退款
     * @param $order_ids
     * @param $user_info
     * @return array
     * @throws \yii\db\Exception
     */
    public function completeRefund($order_ids, $user_info = null)
    {
        //检测订单能否完成退款
        $corder_service = new COrderService();
        $validate_res = $corder_service->validateCompleteRefund($order_ids);
        if (empty($validate_res) || empty($validate_res['y'])) {
            return ['success' => false, 'msg' => '参数错误或没有订单能够退款'];
        }
        $y_order_ids = $validate_res['y'];
        $n_order_ids = $validate_res['n'];

        $order_info_model = new OrderInfoModel();
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            // 修改订单退款信息
            $update_res = $order_info_model->updateOrderAndPayStatus(
                $y_order_ids, ConstantConfig::ORDER_STATUS_CANCEL,
                ConstantConfig::PAY_STATUS_REFUNDED
            );
            if (!$update_res) {
                throw new Exception('订单退款失败');
            }
            $transaction->commit();

            $error_str = '';
            if (!empty($n_order_ids)) {
                $error_ids = join(',', $n_order_ids);
                $error_str = ',下列订单状态错误：' . $error_ids;
            }

            return ['success' => true, 'msg' => '操作成功' . $error_str, 'ids' => $y_order_ids];
        } catch (Exception $e) {
            $transaction->rollBack();
            $connection->close();
            return ['success' => false, 'msg' => '申请退款失败：' . $e->getMessage()];
        }
    }
}