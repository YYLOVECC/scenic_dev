<?php
namespace app\models;

use Yii;
use PDO;

class ModulesModel
{
    private $_id;
    private $_name;
    private $_parent_id;
    private $_title;
    private $_description;
    private $_page_url;
    private $_is_display;
    private $_status;
    private $_created_at;
    private $_updated_at;


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
     * @param mixed $is_display
     */
    public function setIsDisplay($is_display)
    {
        $this->_is_display = $is_display;
    }

    /**
     * @return mixed
     */
    public function getIsDisplay()
    {
        return $this->_is_display;
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
     * @param mixed $page_url
     */
    public function setPageUrl($page_url)
    {
        $this->_page_url = $page_url;
    }

    /**
     * @return mixed
     */
    public function getPageUrl()
    {
        return $this->_page_url;
    }

    /**
     * @param mixed $parent_id
     */
    public function setParentId($parent_id)
    {
        $this->_parent_id = $parent_id;
    }

    /**
     * @return mixed
     */
    public function getParentId()
    {
        return $this->_parent_id;
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
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->_title;
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


    public function create()
    {
        $connection = Yii::$app->db;

        $sql = 'INSERT INTO modules (name,parent_id,description,page_url,is_display,created_at,updated_at)
        VALUES (:name,:parent_id,:description,:page_url,:is_display,:created_at,:updated_at)';
        $command = $connection->createCommand($sql);

        $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
        $command->bindParam(':parent_id', $this->_parent_id, PDO::PARAM_INT);
        $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
        $command->bindParam(':page_url', $this->_page_url, PDO::PARAM_STR);
        $command->bindParam(':is_display', $this->_is_display, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 根据主键ID修改
     * @return int
     */
    public function updateByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'UPDATE modules SET name = :name,page_url = :page_url,is_display = :is_display,parent_id = :parent_id,
        description = :description,updated_at = :updated_at WHERE id=:id';
        $command = $connection->createCommand($sql);

        $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
        $command->bindParam(':page_url', $this->_page_url, PDO::PARAM_STR);
        $command->bindParam(':is_display', $this->_is_display, PDO::PARAM_INT);
        $command->bindParam(':parent_id', $this->_parent_id, PDO::PARAM_INT);
        $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 根据主键ID获取总数
     * @return mixed
     */
    public function countByPk()
    {
        //拼装条件
        $condition = '';

        //占位符数组
        $data = [];

        if ($this->_id) {
            $condition .= 'AND id = :id ';
            $data[':id'] = $this->_id;
        }

        if ($this->_name) {
            $condition .= 'AND name LIKE :name ';
            $data[':name'] = '%' . $this->_name . '%';
        }

        $connection = Yii::$app->db;

        $sql = 'SELECT count(id) AS num FROM modules WHERE status=0 ' . $condition;
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
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

        //拼装条件
        $condition = '';

        //占位符数组
        $data = [];

        if ($this->_id) {
            $condition = 'AND id = :id ';
            $data[':id'] = $this->_id;
        }

        if ($this->_name) {
            $condition .= 'AND name LIKE :name ';
            $data[':name'] = '%' . $this->_name . '%';
        }

        $connection = Yii::$app->db;

        $limit = ' LIMIT ' . intval($page) . ',' . $page_size;
        $sql = 'SELECT * FROM modules WHERE status=0 ' . $condition . 'ORDER BY id DESC' . $limit;

        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryAll();

        return $result;
    }

    /**
     * 批量获取模块名
     * @param $ids
     * @return array
     */
    public function findByIds($ids)
    {
        $connection = Yii::$app->db;

        $sql = 'SELECT id,name FROM modules WHERE status=0 AND id IN (' . $ids . ')';
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();

        return $result;
    }

    /**
     * 获取所有模块名
     * @param int $id
     * @return array
     */
    public function findAll($id = 0)
    {
        $connection = Yii::$app->db;

        $sql = 'SELECT id,name,parent_id FROM modules WHERE status=0 AND id !=' . $id . ' ORDER BY parent_id DESC,id DESC';
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();

        return $result;
    }

    /**
     * 获取单条数据
     * @return array|bool
     */
    public function findByPk(){
        $connection = Yii::$app->db;

        $sql = "SELECT * FROM modules WHERE id=:id";

        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->queryOne();

        return $result;
    }
    /**
     * 获取单条数据
     * @return array|bool
     */
    public function findByUrl(){
        $connection = Yii::$app->db;

        $sql = "SELECT * FROM modules WHERE page_url=:page_url AND status=0";

        $command = $connection->createCommand($sql);
        $command->bindParam(':page_url', $this->_page_url, PDO::PARAM_STR);
        $result = $command->queryOne();

        return $result;
    }

    /**
     * 获取有效模块
     * @return array
     */
    public function getValidModules()
    {
        $connection = Yii::$app->db;

        $sql = 'SELECT id,name,parent_id,page_url FROM modules WHERE status=0 AND is_display=1 ORDER BY parent_id DESC,id DESC';
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();

        return $result;
    }
}