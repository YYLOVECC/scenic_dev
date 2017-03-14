<?php
/**
 * 业务配置
 * Created by PhpStorm.
 * User: yangyue
 * Date: 16-8-18
 * Time: 上午10:30
 */
namespace app\controllers;

//use app\services\func\OperationService;
use app\services\func\OperationService;
use app\services\order\COrderChannelService;
use app\util\ConstantConfig;
use yii\web\HttpException;
use Yii;
use app\components\SuperController;

class OperationController extends SuperController
{
    public function init()
    {
        parent::init();
        //模块权限检测
        $module_url = '/operation';
        $module_id = $this->getModuleIdByUrl($module_url);
        if (!$module_id) {
            throw new HttpException(400);
        }
        $this->module_id = $module_id;
        if (!$this->checkModuleAccess($module_id)) {
            throw new HttpException(400);
        }
    }

    /**
     * 业务配置主页
     * @return string
     * @throws HttpException
     */
    public function actionIndex()
    {
        //获取模块的操作权限
        $actions = $this->getActionKeysByMid($this->module_id);

        $operation_service = new OperationService();
        //获取订单签收/拒收后手机号查看时间限制
        $telephone_limit_date_items = $operation_service->getOperationItems(ConstantConfig::OPERATION_TELEPHONE_LIMIT_DATE);
        if (empty($telephone_limit_date_items)) {
            throw new HttpException(500, '缺少订单签收/拒收后手机号查看时间设置信息');
        }
        $telephone_limit_date = $telephone_limit_date_items[0];

        //获取物流单号查询次数
        $logistics_limit_times_items = $operation_service->getOperationItems(ConstantConfig::OPERATION_LOGISTICS_LIMIT_TIMES);
        if (empty($logistics_limit_times_items)) {
            throw new HttpException(500, '缺少根据物流单号查询订单次数设置信息');
        }
        $logistics_limit_times = $logistics_limit_times_items[0];

        return $this->render('list.twig',
            [
                'actions' => $actions,
                'telephone_limit_date'   => $telephone_limit_date,
                'logistics_limit_times'   => $logistics_limit_times,
            ]);
    }

    /**
     * @return string
     */
    public function actionAjaxList()
    {
        //获取请求参数
        $request = Yii::$app->request;
        $channel_name = $request->post('input_channel_name', '');
        $project_type = $request->post('project_type', 0);
        $start = (int)$request->post('start', 0);
        $page_size = (int)$request->post('page_size', 20);
//        $ordinal_str = $request->post('ordinal_str', '');
//        $ordinal_type = $request->post('ordinal_type', '');
        $c_order_channel_service = new  COrderChannelService();
        $query = ['channel_name'=>$channel_name,'project_type'=>$project_type];
        //分页获取检索数据
        $result = $c_order_channel_service->searchChannelList($query,$start,$page_size);
        return json_encode($result);
    }

    /**
     * 订购渠道保存函数
     * @return string
     * @throws HttpException
     */
    public function actionSave()
    {

        //新增权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'add')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }
        $request = Yii::$app->request;
        $data = [
            "project_type"    => intval($request->post("project_type")),
            "channel_name"    => $request->post("channel_name"),
            "created_at"      => time(),
        ];

        if ($data["project_type"] == 0) {
            return json_encode(["success"=>false,"msg"=>"请选择项目","project_type"=>false]);
        }
        if (empty($data["channel_name"])) {

            return json_encode(["success"=>false,"msg"=>"请输入渠道名称","name"=>'0']);
        }

        $order_channel_service = new  COrderChannelService();
        $count = $order_channel_service->validate($data);
        if ($count>0) {
            return json_encode(["success"=>false,"msg"=>"该渠道已存在"]);
        }
        $result = $order_channel_service ->save($data);
        return json_encode($result);
    }

    /**
     * 订购渠道更新
     * @return string
     * @throws HttpException
     */
    public function actionUpdate()
    {

        //编辑权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'edit')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }
        $request = Yii::$app->request;
        $data = [
            "id"              => $request->post("id"),
            "channel_name"    => $request->post("channel_name"),
            "updated_at"      => time(),
        ];

        if (empty($data["channel_name"])) {
            return json_encode(["success"=>false,"msg"=>"渠道名称不能为空"]);
        }
        $order_channel_service = new  COrderChannelService();
        $result = $order_channel_service ->update($data);
        return json_encode($result);
    }

    /**
     * 订购渠道状态停启用
     */

    public function actionAjaxUpdateState()
    {

        $request = Yii::$app->request;

        // 权限检测
        if($request->getIsAjax() && $request->getIsPost()){

            if (!$this->checkModuleActionAccess($this->module_id, 'enable') || !$this->checkModuleActionAccess($this->module_id, 'disable')) {
                if (Yii::$app->request->isAjax) {
                    return json_encode(["success" => false, "msg" => "无权限操作"]);
                } else {
                    throw new HttpException(400);
                }
            }
        $id = $request->post('id','');
        $state = $request->post('state','');
        $updated_at = time();

        $operation_service = new COrderChannelService();
        $result = $operation_service->updateState($id,$state, $updated_at);
        return json_encode($result);

        }else{
            return json_encode(['success'=>false, 'msg'=>'非法请求']);
        }
    }


    /**
     * 编辑订单手机号可看时间范围
     * @return string
     * @throws HttpException
     */
    public function actionAjaxEditTelephoneLimitDate()
    {
        //编辑权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'edit')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }
        $request = Yii::$app->request;
        //获取post参数
        $operation_id = (int)$request->post('operation_id', 0);
        $operation_item_id = (int)$request->post('operation_item_id', 0);
        $telephone_limit_date = $request->post('telephone_limit_date', 0);
        if ($operation_id <= 0 || $operation_item_id <= 0) {
            return json_encode(["success" => false, "msg" => "参数传递错误"]);
        }
        if(!is_numeric($telephone_limit_date) || intval($telephone_limit_date) < 0){
            return json_encode(["success" => false, "msg" => "订单手机号可看时长必须为大于等于0的整数"]);
        }
        $operation_service = new OperationService();
        $result = $operation_service->editTelephoneLimitDate($operation_id, $operation_item_id, $telephone_limit_date);
        return json_encode($result);
    }

    /**
     * 编辑根据物流单号查询订单次数限制
     * @return string
     * @throws HttpException
     */
    public function actionAjaxEditLogisticsLimitTimes()
    {
        //编辑权限检测
        if (!$this->checkModuleActionAccess($this->module_id, 'edit')) {
            if (Yii::$app->request->isAjax) {
                return json_encode(["success" => false, "msg" => "无权限操作"]);
            } else {
                throw new HttpException(400);
            }
        }
        $request = Yii::$app->request;
        //获取post参数
        $operation_id = (int)$request->post('operation_id', 0);
        $operation_item_id = (int)$request->post('operation_item_id', 0);
        $logistics_limit_times = $request->post('logistics_limit_times', 0);
        if ($operation_id <= 0 || $operation_item_id <= 0) {
            return json_encode(["success" => false, "msg" => "参数传递错误"]);
        }
        if(!is_numeric($logistics_limit_times) || intval($logistics_limit_times) < 0){
            return json_encode(["success" => false, "msg" => "物流单号查询次数必须为大于等于0的整数"]);
        }
        $operation_service = new OperationService();
        $result = $operation_service->editLogisticsLimitTimes($operation_id, $operation_item_id, $logistics_limit_times);
        return json_encode($result);
    }
}
