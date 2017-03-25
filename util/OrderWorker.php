<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/25
 * Time: 16:23
 */
namespace app\util;

use app\utils\GearmanClientParent;

class InventoryWorker extends GearmanClientParent
{
    public function __construct($init = true)
    {
        parent::__construct($init);
    }

    /**
     * 库存服务数据操作
     * @param $data
     * @param bool $async
     * @return InventoryWorker|array
     */
    public function CompleteProcess($data, $async = true)
    {
        if (empty($data)) {
            return ['success' => false, 'msg' => '缺少关键参数'];
        }

        $result = $this->doWork('order_refund_processor', $data, $async);
        return $result;
    }
}