<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/11
 * Time: 16:14
 */
namespace app\services\func;
use app\models\ScenicModel;

class ScenicShowService
{
    public function searchScenicList($params, $ordinal_str, $ordinal_type, $limit = 0, $limit_size = 20)
    {
        //db查询
        $scenic_info_model = new ScenicModel();
        $count = $scenic_info_model->countScenicList($params);
        $scenic_list = $scenic_info_model->searchScenicList($params, $limit, $limit_size, $ordinal_str, $ordinal_type);
        //格式化订单数据
//        $order_list = $this->_formatOrderList($order_list);
        return ['success' => true, 'count' => $count, 'scenic_data' => $scenic_list];
    }
}
