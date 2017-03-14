<?php
namespace app\models;

use PDO;
use Yii;
use yii\db\Exception;

class RoleModulesModel
{
    private $_id;
    private $_role_id;
    private $module_id;
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
    public function getModuleId()
    {
        return $this->module_id;
    }

    /**
     * @param mixed $module_id
     */
    public function setModuleId($module_id)
    {
        $this->module_id = $module_id;
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
     * 根据角色id查询角色模块
     * @return array
     */
    public function getByRoleId()
    {
        $sql = "SELECT * FROM role_modules WHERE role_id=:role_id AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        return $command->queryAll();
    }

    /**
     * 根据角色ids查询角色模块
     * @param $role_ids
     * @return array
     */
    public function getByRoleIds($role_ids)
    {
        if(empty($role_ids)){
            return [];
        }
        $role_id_str = implode(',', $role_ids);

        $sql = "SELECT * FROM role_modules WHERE role_id in(".$role_id_str.") AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    /**
     * 根据角色id查询角色模块
     * @return array
     */
    public function getAllByRoleId()
    {
        $sql = "SELECT * FROM role_modules WHERE role_id=:role_id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        return $command->queryAll();
    }

    /**
     * 根据模块id查询授予该模块权限的所有角色
     * @return array
     */
    public function getByModuleId()
    {
        $sql = "SELECT * FROM role_modules WHERE module_id=:module_id AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);
        return $command->queryAll();
    }

    /**
     * 根据模块id修改角色模块权限状态
     * @param $module_ids
     * @return bool
     */
    public function updateStatusByModuleIds($module_ids)
    {
        if(empty($module_ids)){
            return false;
        }
        $module_id_str = implode(',', $module_ids);

        $connection = Yii::$app->db;
        $sql = "UPDATE role_modules SET status=:status, updated_at=:updated_at
        WHERE role_id=:role_id AND module_id in(".$module_id_str.")";
        try {
            $command = $connection->createCommand($sql);
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
            $command->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 根据模块id修改角色模块权限状态
     * @return bool
     */
    public function updateStatusByMId()
    {
        $connection = Yii::$app->db;
        $sql = "UPDATE role_modules SET status=:status, updated_at=:updated_at
        WHERE module_id=:module_id";
        try {
            $command = $connection->createCommand($sql);
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);
            $command->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 新增功能权限
     * @return bool|int
     */
    public function createBatch()
    {
        //定义SQL
        $sql = "INSERT INTO role_modules (role_id, module_id, status, created_at)
        VALUES (:role_id, :module_id, :status, :created_at)";
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
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);
        $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
        return $command->execute();
    }
}