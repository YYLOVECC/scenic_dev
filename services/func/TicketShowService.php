<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/11
 * Time: 16:15
 */
namespace app\services\func;
use app\models\TicketModel;
use app\util\ConstantConfig;
use Yii;
use yii\base\Exception;

class TicketShowService
{
    public function searchTicketList($params, $ordinal_str, $ordinal_type, $limit = 0, $limit_size = 20)
    {
        //db查询
        $ticket_info_model = new TicketModel();
        $count = $ticket_info_model->countTicketList($params);
        $ticket_info = $ticket_info_model->searchTicketList($params, $limit, $limit_size, $ordinal_str, $ordinal_type);
        //格式化订单数据
        $ticket_list = $this->_formatTicketList($ticket_info, $ordinal_str, $ordinal_type);
        return ['success' => true, 'count' => $count, 'ticket_data' => $ticket_list];
    }

    /**
     * @param $ticket_list
     * @param string $ordinal_st
     * @param string $ordinal_type
     * @return mixed
     */
    public function _formatTicketList($ticket_list, $ordinal_st="", $ordinal_type ="") {
       if (empty($ticket_list)) {
           return [];
       }
        foreach ($ticket_list as &$value) {
            $number = $value['number'];
            if ($number = -1) {
                $value['valid_number'] = '无限制';
            }
        }
        return $ticket_list;
    }
    /**
     * 下架
     * @param $ticket_ids
     * @return array
     */
    public function downTicket($ticket_ids)
    {
        if (empty($ticket_ids)) {
            return ['success'=>false, 'msg'=>'参数传递不完整'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $ticket_model = new TicketModel();
            $ticket_model->setStatus(ConstantConfig::STATUS_DOWN);
            $result = $ticket_model->downTicket($ticket_ids);
            if (!$result) {
                return ['success'=>false, 'msg'=>'状态更新失败'];
            }
            $transaction->commit();
            return ['success'=> true, 'msg'=>'下架成功'];
        } catch(Exception $e) {
            $transaction->rollBack();
            return ['success'=> false, 'msg'=>'下架失败'];
        }
    }
    /**
     *上架
     * @param $ticket_ids
     * @return array
     */
    public function upTicket($ticket_ids)
    {
        if (empty($scenic_ids)) {
            return ['success'=>false, 'msg'=>'参数传递不完整'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $ticket_model = new TicketModel();
            $ticket_model->setStatus(ConstantConfig::STATUS_UP);
            $result = $ticket_model->upTicket($ticket_ids);
            if (!$result) {
                return ['success'=>false, 'msg'=>'状态更新失败'];
            }
            $transaction->commit();
            return ['success'=> true, 'msg'=>'上架成功'];
        } catch(Exception $e) {
            $transaction->rollBack();
            return ['success'=> false, 'msg'=>'上架失败'];
        }
    }
    /**
     * 下架
     * @param $ticket_ids
     * @return array
     */
    public function forceDownTicket($ticket_ids)
    {
        if (empty($ticket_ids)) {
            return ['success'=>false, 'msg'=>'参数传递不完整'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $ticket_model = new TicketModel();
            $ticket_model->setStatus(ConstantConfig::STATUS_FORCE_DOWN);
            $result = $ticket_model->forceDownTicket($ticket_ids);
            if (!$result) {
                return ['success'=>false, 'msg'=>'状态更新失败'];
            }
            $transaction->commit();
            return ['success'=> true, 'msg'=>'下架成功'];
        } catch(Exception $e) {
            $transaction->rollBack();
            return ['success'=> false, 'msg'=>'下架失败'];
        }
    }

}