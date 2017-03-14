<?php
namespace app\models;

use PDO;

use Yii;
use yii\db\Command;
use yii\db\Exception;

class ModuleActionsModel
{
    private $_id;
    private $module_id;
    private $_action_id;
    private $_status;
    private $_created_at;
    private $_updated_at;

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
    public function getActionId()
    {
        return $this->_action_id;
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
     * @param mixed $module_id
     */
    public function setModuleId($module_id)
    {
        $this->module_id = $module_id;
    }

    /**
     * @return mixed
     */
    public function getModuleId()
    {
        return $this->module_id;
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
     * 根据功能权限ID查询
     * @return array
     */
    public function findByModuleId()
    {
        $connection = Yii::$app->db;
        $sql = 'SELECT * FROM module_actions WHERE status=0 AND module_id=:module_id';

        $command = $connection->createCommand($sql);
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);

        return $command->queryAll();
    }

    /**
     * 删除模块操作表的ID
     * @return int
     */
    public function deleteAllByModuleId(){
        $connection = Yii::$app->db;
        $sql = 'DELETE FROM module_actions WHERE module_id=:module_id';

        $command = $connection->createCommand($sql);
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);

        return $command->execute();
    }


    /**
     * 批量创建
     * @return mixed
     */
    public function createBatch()
    {
        //定义SQL
        $sql = 'INSERT INTO module_actions (module_id,action_id,created_at)
         VALUES (:module_id,:action_id,:created_at)';

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
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);
        $command->bindParam(':action_id', $this->_action_id, PDO::PARAM_INT);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 获取有效模块的行为
     * @return array
     */
    public function getValidModuleActions()
    {
        $connection = Yii::$app->db;
        $sql = 'SELECT * FROM module_actions WHERE status=0';

        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    /**
     * 修改模块操作表的状态
     * @return int
     */
    public function updateStatusByActionId(){
        $connection = Yii::$app->db;
        $sql = 'UPDATE module_actions SET status=:status,updated_at = :updated_at WHERE action_id = :action_id';

        $command = $connection->createCommand($sql);
        $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
        $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
        $command->bindParam(':action_id', $this->_action_id, PDO::PARAM_INT);

        return $command->execute();
    }

    /**
     * 批量修改模块操作表的状态
     * @param $action_ids
     * @return bool
     */
    public function updateStatusByActionIds($action_ids)
    {
        if(empty($action_ids)){
            return false;
        }
        $id_str = implode(',', $action_ids);

        $connection = Yii::$app->db;
        $sql = "UPDATE module_actions SET status=:status, updated_at=:updated_at
        WHERE module_id=:module_id AND action_id in(".$id_str.")";
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
     * 获取所有的模块行为
     * @return array
     */
    public function getAllActionsByModuleId()
    {
        $connection = Yii::$app->db;
        $sql = 'SELECT * FROM module_actions where module_id=:module_id';

        $command = $connection->createCommand($sql);
        $command->bindParam(':module_id', $this->module_id, PDO::PARAM_INT);
        return $command->queryAll();
    }
}