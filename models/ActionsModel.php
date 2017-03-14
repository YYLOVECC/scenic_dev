<?php
namespace app\models;

use Yii;
use PDO;

class ActionsModel
{
    private $_id;
    private $_is_enable;
    private $_status;
    private $_name;
    private $_e_name;
    private $_description;
    private $_created_at;
    private $_updated_at;

    /**
     * @return mixed
     */
    public function getIsEnable()
    {
        return $this->_is_enable;
    }

    /**
     * @param mixed $is_enable
     */
    public function setIsEnable($is_enable)
    {
        $this->_is_enable = $is_enable;
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
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @param mixed $e_name
     */
    public function setEName($e_name)
    {
        $this->_e_name = $e_name;
    }

    /**
     * @return mixed
     */
    public function getEName()
    {
        return $this->_e_name;
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
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->_name;
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
     * 根据主键ID获取总数
     * @return mixed
     */
    public function countByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'SELECT count(id) AS num FROM actions';
        $command = $connection->createCommand($sql);
        $result = $command->queryOne();

        return $result['num'];
    }

    /**
     * 获取查询列表页
     * @param int $page
     * @param int $page_size
     * @return mixed
     */
    public function findList($page = 0, $page_size = 0)
    {
        if ($page_size == 0) {
            $page_size = Yii::$app->params['page_size'];
        } else {
            $page_size = intval($page_size);
        }

        $connection = Yii::$app->db;

        $limit = ' LIMIT ' . intval($page) . ',' . $page_size;
        $sql = 'SELECT * FROM actions ORDER BY id DESC' . $limit;
        $command = $connection->createCommand($sql);

        $result = $command->queryAll();

        return $result;
    }

    /**
     * 根据主键ID查询
     * @return array|bool
     */
    public function findByPk()
    {
        $connection = Yii::$app->db;

        $sql = "SELECT * FROM actions WHERE id=:id";

        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->queryOne();

        return $result;
    }

    /**
     * 根据e_name查询
     * @return array|bool
     */
    public function findByEName()
    {
        $connection = Yii::$app->db;

        $sql = "SELECT * FROM actions WHERE e_name=:e_name AND status=0";

        $command = $connection->createCommand($sql);
        $command->bindParam(':e_name', $this->_e_name, PDO::PARAM_STR);
        $result = $command->queryOne();

        return $result;
    }

    /**
     * 根据主键ID修改
     * @return int
     */
    public function updateByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'UPDATE actions SET name = :name,e_name = :e_name,
        description = :description,updated_at = :updated_at WHERE id=:id';
        $command = $connection->createCommand($sql);

        $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
        $command->bindParam(':e_name', $this->_e_name, PDO::PARAM_STR);
        $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 新增
     * @return int
     */
    public function create()
    {
        $connection = Yii::$app->db;

        $sql = 'INSERT INTO actions (name,e_name,description,is_enable,created_at,updated_at)
        VALUES (:name,:e_name,:description,:is_enable,:created_at,:updated_at)';
        $command = $connection->createCommand($sql);

        $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
        $command->bindParam(':e_name', $this->_e_name, PDO::PARAM_STR);
        $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
        $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 获取所有操作
     * @return array
     */
    public function getValidActions(){
        $connection = Yii::$app->db;
        $sql = 'SELECT * FROM actions WHERE status=0 and is_enable=1';
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    public function updateIsEnableByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'UPDATE actions SET is_enable = :is_enable,updated_at = :updated_at WHERE id=:id';
        $command = $connection->createCommand($sql);

        $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);

        return $command->execute();
    }
}