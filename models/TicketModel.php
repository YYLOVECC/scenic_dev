<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/11
 * Time: 14:05
 */
namespace app\models;

use Yii;
use PDO;
use yii\base\Exception;

class TicketModel
{
    private $_id;
    private $_scenic_id;
    private $_scenic_name;
    private $_name;
    private $_price;
    private $_number;
    private $_custom_price;
    private $_valid_time;
    private $_lead_time;
    private $_last_time;
    private $_remark;
    private $_status;
    private $_created_at;
    private $_updated_at;
    private $_table = "ticket";
    private $_column_str = 'id, scenic_id, scenic_name,parent_id, name, price, number, valid_time, lead_time, last_time, remark, status, created_at, updated_at';

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
    public function getScenicName()
    {
        return $this->_scenic_name;
    }

    /**
     * @param mixed $scenic_name
     */
    public function setScenicName($scenic_name)
    {
        $this->_scenic_name = $scenic_name;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->_number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number)
    {
        $this->_number = $number;
    }

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
    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * @param mixed $price
     */
    public function setPrice($price)
    {
        $this->_price = $price;
    }

    /**
     * @return mixed
     */
    public function getCustomPrice()
    {
        return $this->_custom_price;
    }

    /**
     * @param mixed $custom_price
     */
    public function setCustomPrice($custom_price)
    {
        $this->_custom_price = $custom_price;
    }

    /**
     * @return mixed
     */
    public function getValidTime()
    {
        return $this->_valid_time;
    }

    /**
     * @param mixed $valid_time
     */
    public function setValidTime($valid_time)
    {
        $this->_valid_time = $valid_time;
    }

    /**
     * @return mixed
     */
    public function getLeadTime()
    {
        return $this->_lead_time;
    }

    /**
     * @param mixed $lead_time
     */
    public function setLeadTime($lead_time)
    {
        $this->_lead_time = $lead_time;
    }

    /**
     * @return mixed
     */
    public function getLastTime()
    {
        return $this->_last_time;
    }

    /**
     * @param mixed $last_time
     */
    public function setLastTime($last_time)
    {
        $this->_last_time = $last_time;
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
    public function getStatus()
    {
        return $this->_status;
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
     * count 门票列表数量
     * @param $query
     * @return mixed
     */
    public function countTicketList($query)
    {
        $connection = Yii::$app->db;

        $sql = "SELECT count(DISTINCT(ticket.id)) as num FROM ticket LEFT JOIN scenic ON scenic.id = ticket.scenic_id
        RIGHT JOIN users ON users.id = scenic.user_id ";
        //占位符数组
        $data = [];
        $sql .= " WHERE 1=1";

        $c_res = $this->_innerSearchQuery($query);
        $sql .= $c_res['sql'];
        $data = array_merge($data, $c_res['data']);
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryOne();

        return $result['num'];
    }

    /**
     * 获取门票列表数据
     * @param $query
     * @param $ordinal_str
     * @param $ordinal_type
     * @param int $limit
     * @param int $limit_size
     * @return mixed
     */
    public function searchTicketList($query, $limit = 0, $limit_size = 20, $ordinal_str = '', $ordinal_type = '')
    {
        if (empty($ordinal_str)) {
            $ordinal_str = 'created_at';
        }

        if (empty($ordinal_type)) {
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
        $sql = "SELECT ticket.id, ticket.scenic_id, ticket.scenic_name,ticket.parent_id, ticket.name as ticket_name, ticket.price, ticket.number, ticket.valid_time, ticket.lead_time, 
        ticket.last_time, ticket.remark, ticket.status, ticket.created_at, ticket.updated_at, users.name  FROM ticket LEFT JOIN scenic ON scenic.id = ticket.scenic_id LEFT JOIN users 
         on users.id = scenic.user_id ";
        $sql .= " WHERE 1=1";
        $c_res = $this->_innerSearchQuery($query);
        $sql .= $c_res['sql'];
        $data = array_merge($data, $c_res['data']);
        $sql .= ' ORDER BY ' . $ordinal_str . ' ' . $ordinal_type;
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

        //下单时间
        if ($query['created_at_begin'] > 0) {
            $sql .= ' AND ticket.created_at >= :created_at_begin';
            $data[':created_at_begin'] = $query['created_at_begin'];
        }
        if ($query['created_at_end'] > 0) {
            $sql .= ' AND ticket.created_at <= :created_at_end';
            $data[':created_at_end'] = $query['created_at_end'];
        }
        //景点名称
        if (array_key_exists('scenic_id', $query) and $query['scenic_id']>0) {
            $sql .= ' AND scenic.id=:scenic_id';
            $data[':scenic_id'] = $query['scenic_id'];
        }
        if (array_key_exists('ticket_name', $query) and !empty($query['ticket_name'])) {
            $sql .= ' AND instr(ticket.name, :ticket_name)';
            $data[':ticket_name'] = $query['ticket_name'];
        }
        //经销商名称
        if (array_key_exists('user_id', $query) and $query['user_id']>0) {
            $sql .= ' AND scenic.user_id=:user_id';
            $data[':user_id'] = $query['user_id'];
        }
        //门票价格
        if ($query['ticket_price_begin'] > 0) {
            $sql .= ' AND ticket.price >= :ticket_price_begin';
            $data[':ticket_price_begin'] = $query['ticket_price_begin'];
        }
        if ($query['ticket_price_end'] > 0) {
            $sql .= ' AND ticket.price <= :ticket_price_end';
            $data[':ticket_price_end'] = $query['ticket_price_end'];
        }

        return ['sql' => $sql, 'data' => $data];
    }

    /**
     * @param $ticket_ids
     * @return int
     * @throws Exception
     */
    public function downTicket($ticket_ids)
    {
        if (empty($ticket_ids)) {
            return 0;
        }
        if (is_array($ticket_ids)) {
            $ids = implode(',', $ticket_ids);
        } else {
            $ids = $ticket_ids;
        }
        $sql = "UPDATE " . $this->_table . " SET  status=:status WHERE id in (" . $ids . ")";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            return $command->execute();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $ticket_ids
     * @return int
     * @throws Exception
     */
    public function upTicket($ticket_ids)
    {
        if (empty($ticket_ids)) {
            return 0;
        }
        if (is_array($ticket_ids)) {
            $ids = implode(',', $ticket_ids);
        } else {
            $ids = $ticket_ids;
        }
        $sql = "UPDATE " . $this->_table . " SET  status=:status WHERE id in (" . $ids . ")";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            return $command->execute();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $ticket_ids
     * @return int
     * @throws Exception
     */
    public function forceDownTicket($ticket_ids)
    {
        if (empty($ticket_ids)) {
            return 0;
        }
        if (is_array($ticket_ids)) {
            $ids = implode(',', $ticket_ids);
        } else {
            $ids = $ticket_ids;
        }
        $sql = "UPDATE " . $this->_table . " SET  status=:status WHERE id in (" . $ids . ")";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            return $command->execute();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $valid_scenic_ids
     * @return array
     * @throws Exception
     */
    public function getTicketInfo($valid_scenic_ids) {
        if (empty($valid_scenic_ids)) {
            return [];
        }
        if (is_array($valid_scenic_ids)) {
            $ids = implode(',', $valid_scenic_ids);
        } else {
            $ids = $valid_scenic_ids;
        }
        $sql = "SELECT " . $this->_column_str . " FROM ticket WHERE scenic_id in (" . $ids .")";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    /**
     * @param $ticket_id
     * @param $number
     * @return array|int
     * @throws Exception
     */
    public function updateTicketInfo($ticket_id, $number) {
        if (empty($add_ticket_ids)) {
            return [];
        }
        $sql = "UPDATE " . $this->_table . " SET number = number-:number WHERE id = :id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            $command->bindParam(':number', $number, PDO::PARAM_INT);
            $command->bindParam(':id', $ticket_id, PDO::PARAM_INT);
            return $command->execute();
        } catch (Exception $e) {
            throw $e;
        }

    }
}