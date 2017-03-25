<?php
/**
 * 订单后端作业
 * User: teresa
 * Date: 8/28/15
 * Time: 1:52 PM
 */

namespace app\security;

use app\util\ConstantConfig;
use Yii;
use GearmanClient;


class OrderClient
{

    /**
     * Gearman client.
     */
    private $gm_client;

    public function __construct()
    {
        $this->gm_client = Yii::$container->get('gearman')->getConnection();
    }

    /**
     * 申请退款指定天数（7天）后自动完成退款
     * @param $order_ids
     * @param $apply_time: 申请退款时间
     * @return null
     */
    public function automateCompleteRefund($order_ids, $apply_time=0)
    {
        if(empty($order_ids)){
            return false;
        }
        if(is_array($order_ids)){
            $order_ids = implode(',', $order_ids);
        }
        //获取自动完成退款时间
        $return_date = 7;
        if(empty($return_date)){
            return false;
        }
        $apply_time = empty($apply_time)?Yii::$app->params['current_time']:$apply_time;
        $refund_timestamp = intval($apply_time) + intval(floatval($return_date)*24*60*60);
//        $result = $this->_isDoBackground('order_refund_processor', ['order_ids'=>$order_ids,'refund_timestamp'=>$refund_timestamp], true);
        $post_data = ['action' => 'order_refund_processor', 'data' => ['order_ids'=>$order_ids,
            'refund_timestamp'=>$refund_timestamp]];
        $result = $this->_isDoBackground('internal_default_process', $post_data, true);
        if($result['success']){
            return true;
        }
        return false;
    }
    /**
     * 发送Gearman任务方法
     * @param $functionName
     * @param $workload
     * @param bool $type
     * @param $out_time
     * @return array|mixed
     */
    private function _isDoBackground($functionName, $workload, $type = false, $out_time=10000)
    {
        $workload_str = json_encode($workload);
        if ($type) {
            $this->gm_client->doBackground($functionName, $workload_str);
            if ($this->gm_client->returnCode() != GEARMAN_SUCCESS) {
                return ['success' => false, 'msg' => '任务提交失败'];
            } else {
                return ['success' => true];
            }
        } else {
            $this->gm_client->setTimeout($out_time);
            return json_decode($this->gm_client->doNormal($functionName, $workload_str), true);
        }
    }

}
