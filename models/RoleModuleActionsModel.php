<?php
namespace app\models;

use PDO;
use Yii;
use yii\db\Exception;
use yii\sphinx\Command;

class RoleModuleActionsModel
{
    private $_id;
    private $_role_id;
    private $_module_action_id;
    private $module_id;
    private $_action_id;
    private $_status;
    private $_created_at;
    private $_updated_at;

    /**
     * @return mixed
     */
    public function getModuleActionId()
    {
        return $this->_module_action_id;
    }

    /**
     * @param mixed $module_action_id
     */
    public function setModuleActionId($module_action_id)
    {
        $this->_module_action_id = $module_action_id;
    }

    /**
     * @return mixed
     */
    public function getActionId()
    {
        return $this->_action_id;
    }

    /**
     * @param mixed $action_id
     */
    public function setActionId($action_id)
    {
        $this->_action_id = $action_id;
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
     * 根据角色id查询角色模块行为
     * @return array
     */
    public function getByRoleId()
    {
        $sql = "SELECT * FROM role_module_actions WHERE role_id=:role_id AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        return $command->queryAll();
    }

    /**
     * 根据操作id查询角色模块行为
     * @return array
     */
    public function getByActionId()
    {
        $sql = "SELECT * FROM role_module_actions WHERE action_id=:action_id AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':action_id', $this->_action_id, PDO::PARAM_INT);
        return $command->queryAll();
    }


    /**
     * 批量查询角色的模块行为
     * @param $role_ids
     * @return array
     */
    public function getByRoleIds($role_ids)
    {
        if (empty($role_ids)) {
            return [];
        }
        $role_id_str = implode(',', $role_ids);

        $sql = "SELECT * FROM role_module_actions WHERE role_id in(" . $role_id_str . ") AND status=0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }


    /**
     * 根据角色id查询角色模块行为
     * @return array
     */
    public function getAllByRoleId()
    {
        $sql = "SELECT * FROM role_module_actions WHERE role_id=:role_id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindParam(':role_id', $this->_role_id, PDO::PARAM_INT);
        return $command->queryAll();
    }

    /**
     * 根据模块id修改角色模块行为权限状态
     * @param $module_action_ids
     * @param $role_id
     * @return bool
     */
    public function updateStatusByModuleActionIds($module_action_ids, $role_id=null)
    {
        if (empty($module_action_ids)) {
            return false;
        }
        $module_action_id_str = implode(',', $module_action_ids);

        $connection = Yii::$app->db;
        $sql = "UPDATE role_module_actions SET status=:status, updated_at=:updated_at
        WHERE module_action_id in(" . $module_action_id_str . ")";
        $data = [':status' => $this->_status, ':updated_at' => $this->_updated_at];
        if(isset($role_id) and !empty($role_id)){
            $sql .= ' AND role_id=:role_id';
            $data[':role_id'] = $role_id;
        }
        try {
            $command = $connection->createCommand($sql);
            $command->bindValues($data);
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
        $sql = "INSERT INTO role_module_actions (role_id, module_action_id, module_id, action_id, status, created_at)
        VALUES (:role_id, :module_action_id, :module_id, :action_id, :status, :created_at)";
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
        $command->bindParam(':module_action_id', $this->_module_action_id, PDO::PARAM_INT);
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);
        $command->bindParam(':action_id', $this->_action_id, PDO::PARAM_INT);
        $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
        return $command->execute();
    }

    /**
     * 修改模块操作表的状态
     * @return int
     */
    public function updateStatusByActionId()
    {
        $connection = Yii::$app->db;
        $sql = 'UPDATE role_module_actions SET status=:status,updated_at = :updated_at WHERE action_id = :action_id';
        $command = $connection->createCommand($sql);
        $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        $command->bindParam(':action_id', $this->_action_id, PDO::PARAM_INT);

        return $command->execute();
    }
}