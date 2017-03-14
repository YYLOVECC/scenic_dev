<?php
namespace app\models;

use app\util\ConstantConfig;
use Yii;
use PDO;
use yii\base\Exception;

class AdminUsersModel
{
    private $_id;
    private $_email;
    private $_name;
    private $_password;
    private $_salt;
    private $_description;
    private $_create_user_id;
    private $_create_user_name;
    private $_update_user_id;
    private $_update_user_name;
    private $_is_enable;
    private $_status;
    private $_created_at;
    private $_updated_at;
    private $_ids;
    private $_role_id;
    private $_column_str = 'id,email,name,password,salt,description,create_user_id,create_user_name,update_user_id,update_user_name,is_enable,status,created_at,updated_at';

    /**
     * @param mixed $ids
     */
    public function setIds($ids)
    {
        $this->_ids = $ids;
    }

    /**
     * @return mixed
     */
    public function getIds()
    {
        return $this->_ids;
    }


    /**
     * @param mixed $create_user_id
     */
    public function setCreateUserId($create_user_id)
    {
        $this->_create_user_id = $create_user_id;
    }

    /**
     * @return mixed
     */
    public function getCreateUserId()
    {
        return $this->_create_user_id;
    }

    /**
     * @param mixed $create_user_name
     */
    public function setCreateUserName($create_user_name)
    {
        $this->_create_user_name = $create_user_name;
    }

    /**
     * @return mixed
     */
    public function getCreateUserName()
    {
        return $this->_create_user_name;
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
     * @param mixed $is_enable
     */
    public function setIsEnable($is_enable)
    {
        $this->_is_enable = $is_enable;
    }

    /**
     * @return mixed
     */
    public function getIsEnable()
    {
        return $this->_is_enable;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->_email = $email;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->_email;
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
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->_password = $password;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * @param mixed $salt
     */
    public function setSalt($salt)
    {
        $this->_salt = $salt;
    }

    /**
     * @return mixed
     */
    public function getSalt()
    {
        return $this->_salt;
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
     * @param mixed $update_user_id
     */
    public function setUpdateUserId($update_user_id)
    {
        $this->_update_user_id = $update_user_id;
    }

    /**
     * @return mixed
     */
    public function getUpdateUserId()
    {
        return $this->_update_user_id;
    }

    /**
     * @param mixed $update_user_name
     */
    public function setUpdateUserName($update_user_name)
    {
        $this->_update_user_name = $update_user_name;
    }

    /**
     * @return mixed
     */
    public function getUpdateUserName()
    {
        return $this->_update_user_name;
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
     * @param mixed $role_id
     */
    public function setRoleId($role_id)
    {
        $this->_role_id = $role_id;
    }

    /**
     * @return mixed
     */
    public function getRoleId()
    {
        return $this->_role_id;
    }

    /**
     * 创建用户
     */
    public function create()
    {
        $connection = Yii::$app->db;

        $sql = "INSERT INTO admin_users (name,email,password,salt,role_id,is_enable,created_at,updated_at)
        VALUES (:name,:email,:password,:salt,:role_id,:is_enable,:created_at,:updated_at)";

        $command = $connection->createCommand($sql);
        try{
            $command->bindParam(':email', $this->_email, PDO::PARAM_STR);
            $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
            $command->bindParam(':password', $this->_password, PDO::PARAM_STR);
            $command->bindParam(':salt', $this->_salt, PDO::PARAM_STR);
            $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
            $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
            $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->execute();
            return $connection->getLastInsertID();
        } catch (Exception $e){
            return false;
        }

    }

    /**
     * 是否禁用账号
     * @return int
     */
    public function isDisable()
    {
        $connection = Yii::$app->db;

        $sql = "UPDATE admin_users SET is_enable=:is_enable WHERE status=0 AND email=:email";

        $command = $connection->createCommand($sql);
        $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
        $command->bindParam(':email', $this->_email, PDO::PARAM_STR);
        $result = $command->execute();

        return $result;
    }

    /**
     * 根据email获取用户数据
     * @return array|bool
     */
    public function findByEmail()
    {
        $connection = Yii::$app->db;

        $sql = "SELECT * FROM admin_users WHERE status=0 AND email=:email";

        $command = $connection->createCommand($sql);
        $command->bindParam(':email', $this->_email, PDO::PARAM_STR);
        $result = $command->queryOne();

        return $result;
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

        if ($this->_email) {
            $condition .= 'AND email LIKE :email ';
            $data[':email'] = '%' . $this->_email . '%';
        }

        if (in_array($this->_status, [0, 1])) {
            $condition .= 'AND status = :status ';
            $data[':status'] = $this->_status;
        }

        if ($this->_ids) {
            $condition .= 'AND id IN (' . $this->_ids . ')';
        }

        if ($this->_role_id and $this->_role_id > -1) {
            $condition .= 'And id IN (select user_id from user_roles where role_id = ' . $this->_role_id . ') ';
        }

        $connection = Yii::$app->db;
        $sql = 'SELECT count(id) AS num FROM admin_users WHERE status=0 ' . $condition;
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

        if ($this->_email) {
            $condition .= 'AND email LIKE :email ';
            $data[':email'] = '%' . $this->_email . '%';
        }

        if (in_array($this->_status, [0, 1])) {
            $condition .= 'AND status = :status ';
            $data[':status'] = $this->_status;
        }

        if ($this->_ids) {
            $condition .= 'AND id IN (' . $this->_ids . ')';
        }

        if ($this->_role_id and $this->_role_id > -1) {
            $condition .= 'And id IN (select user_id from user_roles where role_id = ' . $this->_role_id . ') ';
        }

        $connection = Yii::$app->db;

        $limit = ' LIMIT ' . intval($page) . ',' . $page_size;
        $sql = 'SELECT * FROM admin_users WHERE 1=1 ' . $condition . 'ORDER BY id DESC' . $limit;

        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryAll();

        return $result;
    }

    public function findByPk()
    {
        $connection = Yii::$app->db;

        $sql = "SELECT * FROM admin_users WHERE status=0 AND id=:id";

        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->queryOne();

        return $result;
    }

    public function updateByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'UPDATE admin_users SET name = :name,email = :email, description = :description, updated_at = :updated_at WHERE id=:id';
        $command = $connection->createCommand($sql);

        $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
        $command->bindParam(':email', $this->_email, PDO::PARAM_STR);
        $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_STR);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        return $command->execute();
    }

    public function updateDisableByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'UPDATE admin_users SET status=0， is_enable = :is_enable WHERE id = :id';
        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 更新用户最新登陆信息
     * @return int
     * @throws \yii\db\Exception
     */
    public function updateLastLoginByPk()
    {
        $connection = Yii::$app->db;

        $sql = 'UPDATE admin_users SET last_login_ip=:last_login_ip, last_login_at = :last_login_at WHERE id = :id';
        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $command->bindParam(':last_login_ip', $this->_last_login_ip, PDO::PARAM_STR);
        $command->bindParam(':last_login_at', $this->_last_login_at, PDO::PARAM_INT);
        return $command->execute();
    }
    /**
     * 根据主键IDS获取用户信息
     * @param $ids
     * @return array
     */
    public function findByPks($ids)
    {
        if (empty($ids)) {
            return [];
        }
        $id_str = implode(',', $ids);

        $connection = Yii::$app->db;
        $sql = "SELECT * FROM admin_users WHERE status=0 AND id in(" . $id_str . ")";
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 修改密码
     * @param $password
     * @param $salt
     * @return bool|int
     */
    public function updatePasswordById($password, $salt)
    {   if (empty($password)) {
            return false;
        }
        $connection = Yii::$app->db;
        $sql = 'UPDATE admin_users SET password = :password, salt = :salt, updated_at = :updated_at WHERE id=:id';
        $command = $connection->createCommand($sql);
        $command->bindParam(':password',$password, PDO::PARAM_STR);
        $command->bindParam(':salt',$salt, PDO::PARAM_STR);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->execute();
        return $result;
    }
    public function getAllUserInfo() {
        $connection = Yii::$app->db;
        $sql = "SELECT name, email from admin_users WHERE status = 0";
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        return $result;

    }
}