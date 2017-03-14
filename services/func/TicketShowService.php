<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/11
 * Time: 16:15
 */
namespace app\services\func;
use app\models\TicketModel;

class TicketShowService
{
    public function searchTicketList($params, $ordinal_str, $ordinal_type, $limit = 0, $limit_size = 20)
    {
        //db查询
        $ticket_info_model = new TicketModel();
        $count = $ticket_info_model->countTicketList($params);
        $ticket_list = $ticket_info_model->searchTicketList($params, $limit, $limit_size, $ordinal_str, $ordinal_type);
        //格式化订单数据
//        $order_list = $this->_formatOrderList($order_list);
        return ['success' => true, 'count' => $count, 'ticket_data' => $ticket_list];
    }

}