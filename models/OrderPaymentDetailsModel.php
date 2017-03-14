<?php
/**
 * Created by PhpStorm.
 * User: teresa
 * Date: 16-10-18
 * Time: 下午4:09
 */

namespace app\models;

use app\util\ArrayUtil;
use app\util\ConstantConfig;
use PDO;
use Yii;
use yii\db\Exception;

class OrderPaymentDetailsModel {
    private $_id;
    private $_order_id;
    private $_order_sn;
    private $_pay_type;
    private $_pay_mode;
    private $_pay_account;
    private $_debit_note;
    private $_pay_at;
    private $_pay_price;
    private $_remark;
    private $_updated_at;
    private $_created_at;

    private $_column_str = 'id,order_id,order_sn,pay_type,pay_mode,pay_account,debit_note,pay_at,pay_price,remark,updated_at,created_at';

    /**
     * @return mixed
     */
    public function getDebitNote()
    {
        return $this->_debit_note;
    }

    /**
     * @param mixed $debit_note
     */
    public function setDebitNote($debit_note)
    {
        $this->_debit_note = $debit_note;
    }


    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
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
    public function getOrderId()
    {
        return $this->_order_id;
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
    public function getOrderSn()
    {
        return $this->_order_sn;
    }

    /**
     * @param mixed $order_sn
     */
    public function setOrderSn($order_sn)
    {
        $this->_order_sn = $order_sn;
    }

    /**
     * @return mixed
     */
    public function getPayType()
    {
        return $this->_pay_type;
    }

    /**
     * @param mixed $pay_type
     */
    public function setPayType($pay_type)
    {
        $this->_pay_type = $pay_type;
    }

    /**
     * @return mixed
     */
    public function getPayMode()
    {
        return $this->_pay_mode;
    }

    /**
     * @param mixed $pay_mode
     */
    public function setPayMode($pay_mode)
    {
        $this->_pay_mode = $pay_mode;
    }

    /**
     * @return mixed
     */
    public function getPayAccount()
    {
        return $this->_pay_account;
    }

    /**
     * @param mixed $pay_account
     */
    public function setPayAccount($pay_account)
    {
        $this->_pay_account = $pay_account;
    }


    /**
     * @return mixed
     */
    public function getPayAt()
    {
        return $this->_pay_at;
    }

    /**
     * @param mixed $pay_at
     */
    public function setPayAt($pay_at)
    {
        $this->_pay_at = $pay_at;
    }

    /**
     * @return mixed
     */
    public function getPayPrice()
    {
        return $this->_pay_price;
    }

    /**
     * @param mixed $pay_price
     */
    public function setPrice($pay_price)
    {
        $this->_pay_price = $pay_price;
    }

    /**
     * @return mixed
     */
    public function getRemark()
    {
        return $this->_remark;
    }

    /**
     * @param mixed $remark
     */
    public function setRemark($remark)
    {
        $this->_remark = $remark;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->_updated_at;
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
    public function getCreatedAt()
    {
        return $this->_created_at;
    }

    /**
     * @param mixed $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->_created_at = $created_at;
    }

    /**
     * 保存订单付款明细
     * @param $order_id
     * @param $sn
     * @param $details
     * @return bool
     * @throws Exception
     */
    public function savePaymentDetails($order_id, $sn, $details)
    {
        if (empty($order_id) || empty($sn) || empty($details)) {
            return false;
        }

        $sql = "INSERT INTO order_payment_details(order_id, sn, pay_type, pay_mode, pay_account, debit_note, pay_at, 
                price, remark, updated_at, created_at) VALUE (:order_id, :sn, :pay_type, :pay_mode, :pay_account, 
                :debit_note, :pay_at, :price, :remark, :updated_at, :created_at)";

        try {
            $connection = Yii::$app->db;
            $command = $connection->createCommand($sql);

            foreach ($details as $value) {
                $command->bindParam(":order_id", $order_id, PDO::PARAM_INT);
                $command->bindParam(":sn", $sn, PDO::PARAM_STR);
                $command->bindParam(":pay_type", $value['pay_type'], PDO::PARAM_INT);
                $command->bindParam(":pay_mode", ArrayUtil::getVal($value, 'pay_mode', ConstantConfig::PAY_MODE_DEFAULT), PDO::PARAM_INT);
                $command->bindParam(":pay_account", ArrayUtil::getVal($value, 'pay_account', ''), PDO::PARAM_STR);
                $command->bindParam(":debit_note", ArrayUtil::getVal($value, 'debit_note', ''), PDO::PARAM_STR);
                $command->bindParam(":pay_at", ArrayUtil::getVal($value, 'pay_at', 0), PDO::PARAM_INT);
                $command->bindParam(":price", $value['price'], PDO::PARAM_STR);
                $command->bindParam(":remark", ArrayUtil::getVal($value, 'remark', ''), PDO::PARAM_STR);
                $command->bindParam(':updated_at', $value['created_at'], PDO::PARAM_INT);
                $command->bindParam(':created_at', $value['created_at'], PDO::PARAM_INT);
                $command->execute();
            }
            return true;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 根据订单id获取订单支付明细
     * @param $order_id
     * @return array
     */
    public function getOrderPaymentDetails($order_id)
    {
        if (empty($order_id)) {
            return [];
        }
        $sql = "SELECT " . $this->_column_str . " FROM order_payment_details WHERE order_id=:order_id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 根据订单ids批量获取订单支付明细
     * @param $order_ids
     * @return array
     */
    public function getOrderPaymentDetailsByOIds($order_ids)
    {
        if (empty($order_ids)) {
            return [];
        }
        if (!is_array($order_ids)) {
            return [];
        }
        $sql = "SELECT " . $this->_column_str . " FROM order_payment_details WHERE order_id in (".implode(',', $order_ids).")";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 批量修改支付明细
     * @param $params:[['id' => xx, 'price' => xx, 'remark' => xx, 'updated_at' => xx], ...]
     * @return bool
     * @throws \Exception
     */
    public function batchUpdateByDetailIds($params){
        if(empty($params)){
            return false;
        }
        $sql = $sql = 'UPDATE order_payment_details SET price=:price, remark=:remark, updated_at=:updated_at WHERE id=:id;';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            foreach ($params as $item) {
                $command->bindParam(':price', $item['price'], PDO::PARAM_STR);
                $command->bindParam(':remark', $item['remark'], PDO::PARAM_STR);
                $command->bindParam(':updated_at', ArrayUtil::getVal($item, 'updated_at', time()), PDO::PARAM_INT);
                $command->bindParam(':id', $item['id'], PDO::PARAM_INT);
                $command->execute();
            }
            return True;
        }catch (\Exception $e){
            throw new \Exception($e);
        }
    }

    /**
     * 删除支付明细
     * @param $detail_ids:支持单个及批量
     * @return bool
     * @throws Exception
     */
    public function delDetails($detail_ids){
        if(empty($detail_ids)){
            return false;
        }
        if (!is_array($detail_ids)) {
            $sql = ' DELETE FROM order_payment_details WHERE id=:id';
        } else {
            $sql = ' DELETE FROM order_payment_details WHERE id in (' .implode(",", $detail_ids). ')';
        }

        try{
            $connection = Yii::$app->db;
            $command = $connection->createCommand($sql);
            if (!is_array($detail_ids)) {
                $command->bindParam(':id', $detail_ids, PDO::PARAM_INT);
            }
            $command->execute();
        }catch (Exception $e){
            throw $e;
        }
        return true;
    }
}