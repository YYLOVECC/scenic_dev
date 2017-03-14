<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 8/7/15
 * Time: 9:28 AM
 */

namespace app\models;

use app\util\ArrayUtil;
use Yii;
use PDO;
use yii\base\Exception;

class OrderDetailsModel
{
    private $_id;
    private $_order_id;
    private $_order_sn;
    private $_scenic_id;
    private $_scenic_name;
    private $_ticket_price;
    private $_ticket_numbers;
    private $_ticket_amount;
    private $_status;
    private $_updated_at;
    private $_created_at;
    private $_column_str = 'id,order_id, order_sn, scenic_id, scenic_name, ticket_name,ticket_price, ticket_numbers, ticket_amount, status, created_at,created_at';

    /**
     * @return mixed
     */
    public function getScenicId()
    {
        return $this->_scenic_id;
    }

    /**
     * @param mixed $scenic_id
     */
    public function setScenicId($scenic_id)
    {
        $this->_scenic_id = $scenic_id;
    }
    /**
     * @return mixed
     */
    public function getScenicName()
    {
        return $this->_scenic_id;
    }

    /**
     * @param mixed $scenic_name
     */
    public function setScenicName($scenic_name)
    {
        $this->_scenic_name = $scenic_name;
    }

    /**
     * @param mixed $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->_created_at = $created_at;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->_created_at;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param mixed $ticket_numbers
     */
    public function setTicketNumbers($ticket_numbers)
    {
        $this->_ticket_numbers = $ticket_numbers;
    }

    /**
     * @return mixed
     */
    public function getTicketNumbers()
    {
        return $this->_ticket_numbers;
    }

    /**
     * @param mixed $order_id
     */
    public function setOrderId($order_id)
    {
        $this->_order_id = $order_id;
    }

    /**
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->_order_id;
    }

    /**
     * @param mixed $ticket_price
     */
    public function setTicketPrice($ticket_price)
    {
        $this->_ticket_price = $ticket_price;
    }

    /**
     * @return mixed
     */
    public function getTicketPrice()
    {
        return $this->_ticket_price;
    }
    /**
     * @param mixed $ticket_amount
     */
    public function setTicketAmount($ticket_amount)
    {
        $this->_ticket_amount = $ticket_amount;
    }

    /**
     * @return mixed
     */
    public function getTicketAmount()
    {
        return $this->_ticket_amount;
    }
    /**
     * @param mixed $sn
     */
    public function setOrderSn($sn)
    {
        $this->_order_sn = $sn;
    }

    /**
     * @return mixed
     */
    public function getOrderSn()
    {
        return $this->_order_sn;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->_status = $status;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->_status;
    }
    /**
     * @param mixed $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->_updated_at = $updated_at;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->_updated_at;
    }

    /**
     * 获取订单明细
     * @param $order_id
     * @return array|null
     */
    public function getOrderDetails($order_id){
        if(empty($order_id)){
            return null;
        }

        $sql = 'SELECT '.$this->_column_str.' FROM order_details WHERE status=0 AND order_id=:order_id';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $result = $command->queryAll();
        return $result;
    }
    public function deleteDetailsByOrderId()
    {
        $sql = ' DELETE FROM order_details WHERE order_id=:order_id';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':order_id',$this->_order_id,PDO::PARAM_INT);
        return $command->execute();
    }

    public function getOrderDetailByIds($order_ids){
        if(empty($order_ids)){
            return null;
        }
        $order_id_str = join(',', $order_ids);
        $sql = 'SELECT '.$this->_column_str.' FROM order_details WHERE status=0 AND order_id in ('.$order_id_str.')';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 更新订单详情的商品id及规格号id
     * @return int
     * @throws \yii\db\Exception
     */
    public function updateMIdByDetailId()
    {
        $sql = 'UPDATE order_details SET merchandise_id=:merchandise_id, specification_id=:specification_id
                where status=0 and id=:id;';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':merchandise_id', $this->_merchandise_id, PDO::PARAM_INT);
        $command->bindParam(':specification_id', $this->_specification_id, PDO::PARAM_INT);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->execute();
        return $result;
    }

    /**
     * 批量修改订单明细商品id
     * @param $params[['detail_id' => xx,'merchandise_id'=>xx,'specification_id'=>xx], ...]
     * @return bool
     * @throws \Exception
     */
    public function batchUpdateMidByDetailIds($params){
        if(empty($params)){
            return false;
        }
        $sql = $sql = 'UPDATE order_details SET merchandise_id=:merchandise_id, specification_id=:specification_id
                where status=0 and id=:id;';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            foreach ($params as $item) {
                $command->bindParam(':merchandise_id', $item['merchandise_id'], PDO::PARAM_INT);
                $command->bindParam(':specification_id', $item['specification_id'], PDO::PARAM_INT);
                $command->bindParam(':id', $item['detail_id'], PDO::PARAM_INT);
                $command->execute();
            }
            return True;
        }catch (\Exception $e){
            throw new \Exception($e);
        }
    }

    /**
     * 批量获取订单明细并以订单编号为键的Map
     * @param $order_ids
     * @return array
     */
    public function getOrderDetailsByOrderIds($order_ids)
    {
        if(empty($order_ids)){
            return null;
        }
        if(is_array($order_ids)){
            $in_order_ids = implode(',', $order_ids);
        }else{
            $in_order_ids = $order_ids;
        }

        $sql = "SELECT ".$this->_column_str." FROM order_details WHERE order_id IN (".$in_order_ids.") AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        if (empty($result)) {
            return  [];
        }

        $result_map = [];
        foreach ($result as $detail) {
            if (!array_key_exists($detail['order_id'], $result_map)) {
                $result_map[$detail['order_id']] = [];
            }
            $result_map[$detail['order_id']][] = $detail;
        }

        return $result_map;
    }

    public function getOrderDetailById($detail_id)
    {
        if(empty($detail_id))
        {
            return null;
        }
        $sql = "SELECT ".$this->_column_str." FROM order_details WHERE id=:id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql,array(':id'=>$detail_id));
        $result = $command->queryOne();
        return $result;
    }
    /**
     * @param $params: [['order_id' => order_id, 'merchandise_sn'=> '', 'specification_code'=>''], []]
     * @return bool
     * @throws \yii\db\Exception
     */
    public function updateInventoryState($params)
    {
        if(empty($params)){
            return false;
        }
        $sql = "UPDATE order_details set inventory_state=:inventory_state WHERE merchandise_sn=:merchandise_sn AND
                specification_code=:specification_code AND order_id=:order_id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        foreach($params as $item){
            $command->bindValue(':inventory_state', $item['inventory_state'], PDO::PARAM_INT);
            $command->bindValue(':merchandise_sn', $item['merchandise_sn'], PDO::PARAM_STR);
            $command->bindValue(':specification_code', ArrayUtil::getVal($item, 'specification_code', ''), PDO::PARAM_STR);
            $command->bindValue(':order_id', $item['order_id'], PDO::PARAM_INT);
            $command->execute();
        }
        return true;
    }


    public function updateInventoryStateInOrders($params)
    {
        if (empty($params)) {
            return false;
        }
        $sql = "UPDATE order_details set inventory_state=:inventory_state WHERE merchandise_sn=:merchandise_sn AND
                specification_code=:specification_code AND order_id in (:order_ids)";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        foreach ($params as $item) {
            $command->bindValue(':inventory_state', $item['inventory_state'], PDO::PARAM_INT);
            $command->bindValue(':merchandise_sn', $item['merchandise_sn'], PDO::PARAM_STR);
            $command->bindValue(':specification_code', ArrayUtil::getVal($item, 'specification_code', ''), PDO::PARAM_STR);
            $command->bindValue(':order_ids', join(',', $item['order_ids']), PDO::PARAM_STR);
            $command->execute();
        }
        return true;
    }


    /**
     * 根据detail_ids修改inventory_state
     * @param $detail_ids
     * @param $inventory_state
     * @return bool
     * @throws \Exception
     */
    public function updateInventoryStateByIds($detail_ids, $inventory_state=0)
    {
        if(empty($detail_ids)){
            return false;
        }
        if(is_array($detail_ids)){
            $detail_id_str = implode(',', $detail_ids);
        }else{
            $detail_id_str = $detail_ids;
        }
        try{
            $sql = "UPDATE order_details set inventory_state=:inventory_state WHERE id IN (" . $detail_id_str .")";
            $connection = Yii::$app->db;
            $command = $connection->createCommand($sql);
            $command->bindValue(':inventory_state', $inventory_state, PDO::PARAM_INT);
            $command->execute();
            return true;
        }catch (Exception $e){
            throw $e;
        }
    }
} 