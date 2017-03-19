<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2/20/17
 * Time: 6:12 PM
 */

namespace app\controllers;

use app\services\order\COrderService;
use app\util\ExcelUtil;
use Yii;
use yii\web\HttpException;
use app\util\ConstantConfig;
use app\components\UserIdentity;
use app\components\SuperController;
use app\services\func\OrderService;
use app\services\func\UsersService;


class OrderController extends SuperController
{

    private $_module_url = '';

    public function init()
    {
        parent::init();
        //模块权限检测
        $module_url = '/order';
        $module_id = $this->getModuleIdByUrl($module_url);
        if (!$module_id) {
            throw new HttpException(400);
        }
        $this->module_id = $module_id;
        if (!$this->checkModuleAccess($module_id)) {
            throw new HttpException(400);
        }
        $this->_module_url = $module_url;
        $this->_resource_type = ConstantConfig::RESOURCE_ORDER;
    }


    /**
     * 订单列表
     * @return string
     */
    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        //获取订单状态查询条件
        $order_status_arr = ConstantConfig::orderStatusArray();
        //获取支付状态
        $pay_status_arr = ConstantConfig::payStatusArray();
        //获取支付方式
        $pay_type_arr = ConstantConfig::payTypeArr();
        //获取支付方式
        $pay_mode_arr = ConstantConfig::payModeArr();
        //获取当前用户用户信息
        $user_info = UserIdentity::getUserInfo();
        //获取所有用户信息
        $user_service = new UsersService();
        $all_user_info = $user_service->getAllUserInfo();
        $distributor_users = [];
        foreach($all_user_info as $user_item) {
            $distributor_users[$user_item['id']] = $user_item['name'];
        }
        //获取审核人
        $user_service = new UsersService();
        $audit_users = $user_service->getAuditUsers();
        $this->css = [
            'libs/dragtable/dragtable.css'
        ];
        $this->scripts = [
            'js/underscore-min.js',
            'libs/kalendae/kalendae.standalone.js',
            'libs/uploadify/jquery.uploadify.min.js',
            'libs/jquery-ui.min.js',
            'libs/dragtable/jquery.dragtable.js',
            'js/order/list.js'];

        return $this->render('list.twig', [
            'actions' => $actions,
            'module_url' => $this->_module_url,
            'audit_users'=> $audit_users,
            'order_status_arr' => $order_status_arr,
            'pay_status_arr' => $pay_status_arr,
            'pay_mode_arr' => $pay_mode_arr,
            'pay_type_arr' => $pay_type_arr,
            'user_info' => $user_info,
            'distributor_users' => $distributor_users
        ]);
    }

    /**
     * 通过ajax列表得到数据
     * @return string
     */
    public function actionAjaxOrderList()
    {
        $request = Yii::$app->request;
        //下单时期日期转换
        $created_at_str = $request->post('created_at_str', '');
        $created_at_begin = 0;
        $created_at_end = 0;
        if (!empty($created_at_str)) {
            $time_list = explode('-', $created_at_str);
            $time_start_str = trim($time_list[0]);
            $time_end_str = count($time_list) > 1 ? trim($time_list[1]) : '';
            $created_at_begin = strtotime($time_start_str);
            $created_at_end = empty($time_end_str) ? 0 : strtotime($time_end_str);
            if ($created_at_end) {
                $created_at_end += 86400;
            }
        }
        $created_at_begin = date('Y-m-d H:i:s', $created_at_begin);
        $created_at_end = date('Y-m-d H:i:s', $created_at_end);
        //应付金额区间转换
        $ticket_price_str = $request->post('ticket_price', '-1');
        $ticket_price_begin = 0;
        $ticket_price_end = 0;
        if($ticket_price_str != '-1'){
            $ticket_price_arr = explode('-', $ticket_price_str);
            $ticket_price_begin = $ticket_price_arr[0];
            $ticket_price_end = count($ticket_price_arr) > 1 ? $ticket_price_arr[1] : 0;

        }
        $id = $request->post('id','');
        $sn = $request->post('sn','');
        $scenic_name = $request->post('scenic_name', '');
        $mobile = $request->post('mobile','');
        $tourist_name = $request->post('tourist_name','');
        $audit_user_id = $request->post('audit_user_id',0);
        $order_status = $request->post('order_status', 0); //订单状态
        $pay_status = $request->post('pay_status', -1);//支付状态
        $distributor_id = $request->post('distributor_id',0);
        $ordinal_str = $request->post('ordinal_str', '');
        $ordinal_type = $request->post('ordinal_type', '');
        $limit = $request->post('start', 0);
        $limit_size = $request->post('page_size', 20);
        $query = [
            'id' => $id,
            'created_at_begin' => $created_at_begin,
            'created_at_end' => $created_at_end,
            'sn' => $sn,
            'scenic_name' => $scenic_name,
            'mobile' => $mobile,
            'tourist_name' => $tourist_name,
//            'user_id' => $distributor_id,
            'audit_user_id' => $audit_user_id,
            'order_status' => $order_status,
            'pay_status' => $pay_status,
            'distributor_id' => $distributor_id,
            'ticket_price_begin' => $ticket_price_begin,
            'ticket_price_end' => $ticket_price_end,
        ];
        $order_service = new OrderService();
        $result = $order_service->searchOrderList($query, $ordinal_str, $ordinal_type, $limit, $limit_size);
        return json_encode($result);
    }
    /**
     * 订单详情
     * @return string
     * @throws \yii\web\HttpException
     */
    public function actionDetail()
    {
        $request = Yii::$app->request;
        $id = intval($request->get('id', 0));
        if ($id <= 0 ) {
            throw new HttpException(404, '该页面不存在!');
        }

        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);
        $order_service = new OrderService();
        $res_data = $order_service->getOrderInfo($id);
        if (empty($res_data)) {
            throw new HttpException(500, '订单id不存在或已经被删除');
        }
        $current_login_user = UserIdentity::getUserInfo();
        $order_audit_user = null;
        $order_info = $res_data['order_info'];
        if ($order_info['order_status'] == ConstantConfig::ORDER_STATUS_DEFAULT
            or $order_info['order_status'] == ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION
        ) {
            $order_audit_user_id = $current_login_user['id'];
            $user_service = new UsersService();
            $order_audit_user = $user_service->findByPk($order_audit_user_id);
        }
        $this->scripts = [
            'js/order/info.js'];
        $data = [
            'actions' => $actions,
            'order_info' => $res_data['order_info'],
            'order_details' => $res_data['order_details'],
            'order_payment_details' => $res_data['payment_details'],
            'module_url' => $this->_module_url,
            'current_audit_user' => $order_audit_user
        ];

        return $this->render('info.twig', $data);
    }

    /**
     * 订单客审
     * @throws \yii\web\HttpException
     * @return string
     */
    public function actionAjaxToExamine()
    {
        //客审权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'audit')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }

        $request = Yii::$app->request;
        $id_str = $request->post('id', '');
        if (empty($id_str)) {
            return json_encode(['success' => false, 'msg' => '参数传递错误']);
        }
        $ids = explode(',', $id_str);
        $user_info = $this->user_info;
        $user_id = $user_info['id'];
        $user_name = $user_info['name'];
        $order_service = new OrderService();
        $res = $order_service->toOrCancelExampleOrder($ids, ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE, $user_id,
            $user_name);
        if($res["success"]){
            $this->saveResourceLogs($res["ids"]);
        }

        return json_encode($res);
    }

    /**
     * 订单反审
     * @throws \yii\web\HttpException
     * @return string
     */
    public function actionAjaxCancelExamine()
    {
        //订单反审权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'review')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }
        $request = Yii::$app->request;
        $id_str = $request->post('id', '');
        if (empty($id_str)) {
            return json_encode(['success' => false, 'msg' => '参数传递错误']);
        }
        $ids = explode(',', $id_str);

        $user_info = $this->user_info;
        $user_id = $user_info['id'];
        $user_name = $user_info['name'];
        $order_service = new OrderService();
        $res = $order_service->toOrCancelExampleOrder($ids, ConstantConfig::CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE,
            $user_id, $user_name);
        if($res["success"]){
            $this->saveResourceLogs($res["ids"]);
        }

        return json_encode($res);
    }

    /**
     * 退款单审核
     * @return string
     * @throws HttpException
     */

    public function actionAjaxOrderRefundAudit()
    {
        if (!$this->checkModuleActionAccess($this->module_id, 'refund_audit')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }

        }
        $request = Yii::$app->request;
        $id_str = $request->post('ids');
        if (empty($id_str)) {
            return json_encode(['success' => false, 'msg' => "参数传递错误"]);
        }
        $service_ids = explode(',', $id_str);
        $user_info = UserIdentity::getUserInfo();
        $order_service = new OrderService();
        $res = $order_service->refundAudit($service_ids, $user_info);
        //资源操作日志
        if ($res["success"]) {
            $this->saveResourceLogs($res["ids"]);
        }
        return json_encode($res);
    }
    /**
     * 列表数据导出
     * @return string
     * @throws HttpException
     */
    public function actionAjaxExportData()
    {
        if (!$this->checkModuleActionAccess($this->module_id, 'export')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无导出权限"]);
            } else {
                throw new HttpException(400, "无导出权限");
            }
        }

        $request = Yii::$app->request;

        //下单时期日期转换
        $created_at_str = $request->get('created_at_str', '');
        $created_at_begin = 0;
        $created_at_end = 0;
        if (!empty($created_at_str)) {
            $time_list = explode('-', $created_at_str);
            $time_start_str = trim($time_list[0]);
            $time_end_str = count($time_list) > 1 ? trim($time_list[1]) : '';
            $created_at_begin = strtotime($time_start_str);
            $created_at_end = empty($time_end_str) ? 0 : strtotime($time_end_str);
            if ($created_at_end) {
                $created_at_end += 86400;
            }
        }
        $created_at_begin = date('Y-m-d H:i:s', $created_at_begin);
        $created_at_end = date('Y-m-d H:i:s', $created_at_end);
        //应付金额区间转换
        $ticket_price_str = $request->get('ticket_price', '-1');
        $ticket_price_begin = 0;
        $ticket_price_end = 0;
        if($ticket_price_str != '-1'){
            $ticket_price_arr = explode('-', $ticket_price_str);
            $ticket_price_begin = $ticket_price_arr[0];
            $ticket_price_end = count($ticket_price_arr) > 1 ? $ticket_price_arr[1] : 0;

        }
        $sn = $request->get('sn','');
        $scenic_name = $request->get('scenic_name', '');
        $mobile = $request->get('mobile','');
        $tourist_name = $request->get('tourist_name','');
        $audit_user_id = $request->get('audit_user_id',0);
        $order_status = $request->get('order_status', 0); //订单状态
        $distributor_id = $request->get('distributor_id',0);
        $ordinal_str = $request->post('ordinal_str', '');
        $ordinal_type = $request->post('ordinal_type', '');

        $params = [
            'created_at_begin' => $created_at_begin,
            'created_at_end' => $created_at_end,
            'sn' => $sn,
            'scenic_name' => $scenic_name,
            'mobile' => $mobile,
            'tourist_name' => $tourist_name,
            'user_id' => $distributor_id,
            'audit_user_id' => $audit_user_id,
            'order_status' => $order_status,
            'ticket_price_begin' => $ticket_price_begin,
            'ticket_price_end' => $ticket_price_end,
        ];
        //订单ids
        $id_str = $request->get('ids');
        if (!empty($id_str)) {
            $params['id'] = explode(',', $id_str);
        }
        //获取导出采购单数据
        $purchase_order_service = new OrderService();
        $result = $purchase_order_service->exportData($params, $ordinal_str, $ordinal_type);

        if ($result['success'] && !empty($result['data'])) {
            //操作日志
            $this->saveResourceLogs($result['ids']);

            $excel_util = new ExcelUtil();
            $c_date = date('YmdHis'); //时间
            $header = ['预定时间', '订单号', '经销商', '景区名称', '订单状态', '支付状态', '游客姓名', '手机号码', '应付金额', '客审人','门票名称',
                       '门票金额', '门票数量', '门票总额', '买家备注'];
            $file_path = $excel_util->writerExcel($c_date, $result['data'], $header);
            $excel_util->downloadFile($file_path);
            return true;
        } else {
            return json_encode(["success" => false, "msg" => "获取数据失败"]);
        }
    }
    /**
     * 获取订单的修改日志
     * @return string
     */
    public function actionAjaxActionLogs()
    {
        $request = Yii::$app->request;
        $id = intval($request->post('id', 0));
        if (empty($id)) {
            return json_encode(['success' => false, 'msg' => '参数传递错误']);
        }
        $corder_service = new COrderService();
        $res = $corder_service->getOrderActionLogs($id);
        return json_encode($res);
    }



}