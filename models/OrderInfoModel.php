<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2/21/17
 * Time: 1:50 PM
 */

namespace app\models;

use app\util\ConstantConfig;
use Yii;
use PDO;
use yii\base\Exception;


class OrderInfoModel
{
    private $_id;
    private $_sn;
    private $_pay_mode;
    private $_pay_type;
    private $_pay_status;
    private $_pay_at;
    private $_order_status;
    private $_mobile;
    private $_ticket_price;
    private $_audit_user_id;
    private $_audit_user_name;
    private $_remark;
    private $_status;
    private $_updated_at;
    private $_created_at;

    private $_column_str = 'id,sn,tourist_name,user_id,scenic_id,scenic_name,mobile,distributor_id,distributor_name,
                            pay_price,paid_price,pay_status,order_type,order_status,admission_time,play_time,
                            audit_user_id,audit_user_name,audit_at,remark,status,updated_at,created_at';

    private $_ordinal_str_array = array('created_at', 'pay_mode', 'pay_type', 'pay_status',
        'ticket_price', 'order_status');

    private $_ordinal_type_array = array('ASC', 'DESC');

    private $_edit_field_array = array('pay_mode' => "支付途径", 'pay_type' => "支付方式",
        'remark' => "卖家备注");

    /**
     * @return mixed
     */
    public function getAuditUserId()
    {
        return $this->_audit_user_id;
    }

    /**
     * @param mixed $audit_user_id
     */
    public function setCreateUserId($audit_user_id)
    {
        $this->_audit_user_id = $audit_user_id;
    }

    /**
     * @return mixed
     */
    public function getAuditUserName()
    {
        return $this->_audit_user_name;
    }

    /**
     * @param mixed $audit_user_name
     */
    public function setCreateUserName($audit_user_name)
    {
        $this->_audit_user_name = $audit_user_name;
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
     * @param mixed $mobile
     */
    public function setMobile($mobile)
    {
        $this->_mobile = $mobile;
    }

    /**
     * @return mixed
     */
    public function getMobile()
    {
        return $this->_mobile;
    }

    /**
     * @param mixed $order_status
     */
    public function setOrderStatus($order_status)
    {
        $this->_order_status = $order_status;
    }

    /**
     * @return mixed
     */
    public function getOrderStatus()
    {
        return $this->_order_status;
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
    public function getPayAt()
    {
        return $this->_pay_at;
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
    public function getPayMode()
    {
        return $this->_pay_mode;
    }

    /**
     * @param mixed $ticket_price
     */
    public function setPayPrice($ticket_price)
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
     * @param mixed $pay_status
     */
    public function setPayStatus($pay_status)
    {
        $this->_pay_status = $pay_status;
    }

    /**
     * @return mixed
     */
    public function getPayStatus()
    {
        return $this->_pay_status;
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
    public function getPayType()
    {
        return $this->_pay_type;
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
    public function getRemark()
    {
        return $this->_remark;
    }

    /**
     * @param mixed $sn
     */
    public function setSn($sn)
    {
        $this->_sn = $sn;
    }

    /**
     * @return mixed
     */
    public function getSn()
    {
        return $this->_sn;
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
     * @return array
     */
    public function getEditFieldArray()
    {
        return $this->_edit_field_array;
    }


    /**
     * count 订单列表数量
     * @param $query
     * @return mixed
     */
    public function countOrderList($query)
    {
        $connection = Yii::$app->db;

        $sql = "SELECT count(DISTINCT(oi.id)) as num FROM order_info oi";
        //占位符数组
        $data = [];
        $sql .= " WHERE oi.status = 0";

        $c_res = $this->_innerSearchQuery($query);
        $sql .= $c_res['sql'];
        $data = array_merge($data, $c_res['data']);
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryOne();

        return $result['num'];
    }

    /**
     * 获取订单列表数据
     * @param $query
     * @param $ordinal_str
     * @param $ordinal_type
     * @param int $limit
     * @param int $limit_size
     * @return mixed
     */
    public function searchOrderList($query, $limit = 0, $limit_size = 20, $ordinal_str = '', $ordinal_type = '')
    {
        if (empty($ordinal_str) || !in_array($ordinal_str, $this->_ordinal_str_array)) {
            $ordinal_str = 'created_at';
        }

        if (empty($ordinal_type) || !in_array($ordinal_type, $this->_ordinal_type_array)) {
            $ordinal_type = 'DESC';
        }

        if (empty($limit)) {
            $limit = 0;
        }

        if (empty($limit_size)) {
            $limit_size = 20;
        }

        //占位符数组
        $data = [];

        $connection = Yii::$app->db;
        $sql = "SELECT oi.* FROM order_info oi ";
        $sql .= " WHERE oi.status=0 ";
        $c_res = $this->_innerSearchQuery($query);
        $sql .= $c_res['sql'];
        $data = array_merge($data, $c_res['data']);
        $sql .= ' ORDER BY oi.' . $ordinal_str . ' ' . $ordinal_type;
        $sql .= ' LIMIT ' . $limit . ',' . $limit_size;
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryAll();

        return $result;
    }

    private function _innerSearchQuery($query)
    {
        $sql = '';
        $data = [];
        if (array_key_exists('id', $query) and !empty($query['id'])) {
            $id = $query['id'];
            if (is_array($id) and count($id) > 1) {
                $sql .= " AND id in(" . implode(',', $id) . ")";
            } else {
                if (is_array($id)) {
                    $id = $id[0];
                }
                $sql .= " AND id=:id";
                $data['id'] = $id;
            }

        } elseif (array_key_exists('sn', $query) and !empty($query['sn'])) {
            //订单号
            $sql .= " AND instr(oi.sn, '" . $query['sn'] . "')";
        } else {
            //下单时间
            if ($query['created_at_begin'] > 0) {
                $sql .= ' AND oi.created_at >= :created_at_begin';
                $data[':created_at_begin'] = $query['created_at_begin'];
            }
            if ($query['created_at_end'] > 0) {
                $sql .= ' AND oi.created_at <= :created_at_end';
                $data[':created_at_end'] = $query['created_at_end'];
            }
            //订单状态
            if (isset($query['order_status']) and $query['order_status'] >= 0) {
                $sql .= ' AND oi.order_status = :order_status';
                $data[':order_status'] = $query['order_status'];
            }
            //付款状态
            if (isset($query['pay_status']) and $query['pay_status'] >= 0) {
                $sql .= ' AND oi.pay_status = :pay_status';
                $data[':pay_status'] = $query['pay_status'];
            }

            //手机号码
            if (array_key_exists('mobile', $query) and !empty($query['mobile'])) {
                $sql .= ' AND instr(oi.mobile, :mobile)';
                $data[':mobile'] = $query['mobile'];
            }
            //景点名称
            if (array_key_exists('scenic_name', $query) and !empty($query['scenic_name'])) {
                $sql .= ' AND oi.scenic_name=:scenic_name';
                $data[':scenic_name'] = $query['scenic_name'];
            }
            //游客名称
            if (array_key_exists('tourist_name', $query) and !empty($query['tourist_name'])) {
                $sql .= ' AND instr(oi.tourist_name, :tourist_name)';
                $data[':tourist_name'] = $query['tourist_name'];
            }
            //经销商名称
            if (array_key_exists('distributor_name', $query) and !empty($query['distributor_name'])) {
                $sql .= ' AND oi.distributor_name=:distributor_name';
                $data[':distributor_name'] = $query['distributor_name'];
            }
            //客审人
            if (array_key_exists('audit_user_id', $query) and $query['audit_user_id'] > 0) {
                $sql .= ' AND oi.audit_user_id=:audit_user_id';
                $data[':audit_user_id'] = $query['audit_user_id'];
            }
            //门票价格
            if ($query['pay_price_begin'] > 0) {
                $sql .= ' AND oi.pay_price >= :pay_price_begin';
                $data[':pay_price_begin'] = $query['pay_price_begin'];
            }
            if ($query['pay_price_end'] > 0) {
                $sql .= ' AND oi.pay_price <= :pay_price_end';
                $data[':pay_price_end'] = $query['pay_price_end'];
            }
        }
        return ['sql' => $sql, 'data' => $data];
    }

    /**
     * 根据订单id获取订单信息
     * @return array|bool
     */
    public function getOrderInfo()
    {
        $sql = 'SELECT ' . $this->_column_str . ' FROM order_info WHERE status=0 AND id=:id ';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->queryOne();
        return $result;
    }

    public function getOrderInfoList($order_ids)
    {
        if (empty($order_ids)) {
            return null;
        }
        if (is_array($order_ids)) {
            $in_order_ids = implode(',', $order_ids);
        } else {
            $in_order_ids = $order_ids;
        }

        $sql = 'SELECT ' . $this->_column_str . ' FROM order_info WHERE status=0 AND id in (' . $in_order_ids . ')';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 修改订单状态及客审信息
     * @param $y_order_ids
     * @param $order_status
     * @param int $audit_user_id
     * @param string $audit_user_name
     * @return bool|int
     */
    public function updateOrderStatusAndInfo($y_order_ids, $order_status, $audit_user_id = 0, $audit_user_name = '')
    {
        if (empty($y_order_ids)) {
            return 0;
        }
        $sql = " UPDATE order_info SET updated_at=:updated_at , order_status=:order_status";
        if (!empty($audit_user_id) && !empty($audit_user_name)) {
            $sql .= ', audit_user_id=:audit_user_id, audit_user_name=:audit_user_name, audit_at=:audit_at ';
        }
        if (is_array($y_order_ids)) {
            $ids = implode(',', $y_order_ids);
        } else {
            $ids = $y_order_ids;
        }
        $sql .= ' WHERE id in (' . $ids . ')';
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            $cur_time = time();
            $updated_at = date('Y-m-d H:i:s', $cur_time);
            $command->bindParam(':updated_at', $updated_at, PDO::PARAM_STR);
            $command->bindParam(':order_status', $order_status, PDO::PARAM_INT);
            if (!empty($audit_user_id) && !empty($audit_user_name)) {
                $command->bindParam(':audit_user_id', $audit_user_id, PDO::PARAM_INT);
                $command->bindParam(':audit_user_name', $audit_user_name, PDO::PARAM_STR);
                $command->bindParam(':audit_at', $cur_time, PDO::PARAM_INT);
            }
            $command->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }

    }

    /**
     * 修改付款状态
     * @param $order_ids
     * @param $pay_status
     * @return bool|int
     * @throws Exception
     * @throws \Exception
     */
    public function updatePayStatus($order_ids, $pay_status)
    {
        if (empty($order_ids) || empty($pay_status)) {
            return 0;
        }
        $all_pay_status = ConstantConfig::allPayStatus();
        if (!in_array($pay_status, $all_pay_status)) {
            return 0;
        }
        if (is_array($order_ids)) {
            $in_order_ids = implode(',', $order_ids);
        } else {
            $in_order_ids = $order_ids;
        }

        $sql = " UPDATE order_info SET updated_at=:updated_at, pay_status=:pay_status WHERE id in (" . $in_order_ids . ")";
        $connection = Yii::$app->db;
        try {

            $command = $connection->createCommand($sql);
            $cur_time = time();
            $command->bindParam(':updated_at', $cur_time, PDO::PARAM_INT);
            $command->bindParam(':pay_status', $pay_status, PDO::PARAM_INT);
            $command->execute();
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function updateOrderPayStatus()
    {
        $sql = "UPDATE order_info SET updated_at=:updated_at , pay_status=:pay_status, order_status=:order_status WHERE id=:id";

        $connection = Yii::$app->db;
        try {
            $command = $connection->createCommand($sql);

            $updated_at = date('Y-m-d H:i:s', time());
            $command->bindValue(":updated_at", $updated_at);
            $command->bindValue(":pay_status", $this->getPayStatus());
            $command->bindValue(":order_status", $this->getOrderStatus());
            $command->bindValue(":id", $this->getId());
            $command->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新退款状态
     * @param $order_ids
     * @param $pay_status
     * @return bool|int
     * @throws Exception
     */
    public function updateRefundStatus($order_ids, $pay_status)
    {
        if (empty($order_ids) || empty($pay_status)) {
            return 0;
        }
        if (is_array($order_ids)) {
            $in_order_ids = implode(',', $order_ids);
        } else {
            $in_order_ids = $order_ids;
        }

        $sql = " UPDATE order_info SET updated_at=:updated_at, pay_status=:pay_status WHERE id in (" . $in_order_ids . ")";
        $connection = Yii::$app->db;
        try {

            $command = $connection->createCommand($sql);
            $cur_time = time();
            $update_at = date('Y-m-d H:i:s', $cur_time);
            $command->bindParam(':updated_at', $update_at, PDO::PARAM_INT);
            $command->bindParam(':pay_status', $pay_status, PDO::PARAM_INT);
            $command->execute();
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 获取支付方式未在线支付的待付款订单
     * @param $created_limit_time
     * @return array
     */
    public function getOnlineUnpaidOrder($created_limit_time)
    {
        if (empty($created_limit_time)) {
            return [];
        }
        $created_limit_time = date('Y-m-d H:i:s', $created_limit_time);
        $sql = "SELECT * FROM order_info WHERE  pay_status =:pay_status  AND
                order_status=:order_status AND status=0 AND order_type!=3 AND created_at < :limit_time";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':pay_status', $this->_pay_status, PDO::PARAM_INT);
        $command->bindParam(':order_status', $this->_order_status, PDO::PARAM_INT);
        $command->bindParam(':limit_time', $created_limit_time, PDO::PARAM_STR);
        return $result = $command->queryAll();
    }

    /**
     * @param $y_order_ids
     * @param $order_status
     * @return bool|int
     * @throws Exception
     */
    public function updateOrderStatus($y_order_ids, $order_status)
    {
        $all_order_status = ConstantConfig::orderStatusArray();
        if (empty($y_order_ids) || !array_key_exists($order_status, $all_order_status)) {
            return 0;
        }
        $sql = " UPDATE order_info SET updated_at=:updated_at , order_status=:order_status";
        if (is_array($y_order_ids)) {
            $ids = implode(',', $y_order_ids);
        } else {
            $ids = $y_order_ids;
        }
        $sql .= ' WHERE id in (' . $ids . ')';
        $connection = Yii::$app->db;
        try {

            $command = $connection->createCommand($sql);
            $cur_time = time();
            $update_at = date('Y-m-d H:i:s', $cur_time);
            $command->bindParam(':updated_at', $update_at, PDO::PARAM_INT);
            $command->bindParam(':order_status', $order_status, PDO::PARAM_INT);
            $command->execute();
            return true;
        } catch (Exception $e) {
            throw $e;
        }

    }

    /**
     * @param $order_ids
     * @param $order_status
     * @param $pay_status
     * @return int
     * @throws Exception
     */
    public function updateOrderAndPayStatus($order_ids, $order_status, $pay_status)
    {
        if (empty($order_ids) || empty($order_status) || empty($pay_status)) {
            return 0;
        }
        if (is_array($order_ids)) {
            $in_order_ids = implode(',', $order_ids);
        } else {
            $in_order_ids = $order_ids;
        }
        $sql = " UPDATE order_info SET updated_at=:updated_at,order_status=:order_status, pay_status=:pay_status
                 WHERE id in (" . $in_order_ids . ")";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $cur_time = time();
        $updated_at = date('Y-m-d H:i:s', $cur_time);
        try {
            $command->bindParam(':updated_at', $updated_at, PDO::PARAM_INT);
            $command->bindParam(':order_status', $order_status, PDO::PARAM_INT);
            $command->bindParam(':pay_status', $pay_status, PDO::PARAM_INT);
            return $command->execute();
        } catch (Exception $e) {
            throw $e;
        }
    }
}