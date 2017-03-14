<?php

namespace app\models;

use PDO;

use Yii;
use yii\db\Command;
use yii\db\Exception;

use app\util\ConstantConfig;

class UserRolesModel
{
    private $_id;
    private $_user_id;
    private $_role_id;
    private $_status;
    private $_created_at;
    private $_updated_at;

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
    public function getUserId()
    {
        return $this->_user_id;
    }

    /**
     * @param mixed $user_id
     */
    public function setUserId($user_id)
    {
        $this->_user_id = $user_id;
    }

    /**
     * @return mixed
     */
    public function getRoleId()
    {
        return $this->_role_id;
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
     * 批量修改角色用户关系
     * @param $role_ids
     * @return bool
     */
    public function updateStatusByRids($role_ids)
    {
        if(empty($role_ids)){
            return false;
        }
        $id_str = implode(',', $role_ids);
        $connection = Yii::$app->db;
        $sql = "UPDATE user_roles SET status=:status, updated_at=:updated_at WHERE role_id in (".$id_str.")";
        try{
            $command = $connection->createCommand($sql);
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->execute();
            return true;
        }catch (Exception $e){
            return false;
        }
    }

    /**
     * 根据角色Id 及用户id查询角色用户关联
     */
    public function getByUserId()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM user_roles WHERE status=:status AND user_id=:user_id AND role_id=:role_id";
        $command = $connection->createCommand($sql);
        $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
        $command->bindParam(':user_id', $this->_user_id, PDO::PARAM_INT);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        return $command->queryOne();
    }

    /**
     * 根据角色Ids获取角色关联的用户
     * @param $role_ids
     * @return array|bool
     */
    public function getByRoleIds($role_ids)
    {
        if(empty($role_ids)){
            return [];
        }
        $id_str = implode(',', $role_ids);

        $connection = Yii::$app->db;
        $sql = "SELECT * FROM user_roles WHERE status=0 AND role_id in(". $id_str .")";
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    /**
     * 根据角色Id角色关联的用户
     */
    public function getByRoleId()
    {

        $connection = Yii::$app->db;
        $sql = "SELECT * FROM user_roles WHERE status=0 AND role_id=:role_id";
        $command = $connection->createCommand($sql);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        return $command->queryAll();
    }


    /**
     * 删除相关角色
     * @return array|bool
     */
    public function deleteByUserId()
    {
        $connection = Yii::$app->db;

        $sql = 'DELETE FROM user_roles WHERE user_id=:user_id';
        $command = $connection->createCommand($sql);
        $command->bindParam(':user_id', $this->_user_id, PDO::PARAM_INT);
        return $command->execute();
    }


    /**
     * 批量创建
     * @return mixed
     */
    public function createBatch()
    {
        //定义SQL
        $sql = 'INSERT INTO user_roles (user_id,role_id,created_at,updated_at)
         VALUES (:user_id,:role_id,:created_at,:updated_at)';

        //连接数据库查询数据
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);

        return $command;
    }

    /**
     * 批量执行
     * @param $command
     * @return mixed
     */
    public function createBatchExecute($command)
    {
        $command->bindParam(':user_id', $this->_user_id, PDO::PARAM_INT);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
        return $command->execute();
    }

    /**
     * 根据用户id获取用户角色
     * @return array|bool
     */
    public function getRoleByUserId()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM user_roles WHERE status=0 AND user_id=:user_id";
        $command = $connection->createCommand($sql);
        $command->bindParam(':user_id', $this->_user_id, PDO::PARAM_INT);
        return $command->queryAll();
    }

    /**
     * 获取所有中间表的数据
     * @return array
     */
    public function getAllUserRole()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM user_roles WHERE status = 0";
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    /**
     * 插入数据
     * @return int
     */
    public function create() {
        $connection = Yii::$app->db;
        $sql = 'INSERT INTO user_roles (user_id,role_id,created_at,updated_at)
         VALUES (:user_id,:role_id,:created_at,:updated_at)';
        $command = $connection->createCommand($sql);
        $command->bindParam(':user_id', $this->_user_id, PDO::PARAM_INT);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        return $command->execute();


    }

}