<?php
/**
 * User: bcpmai
 * Date: 15-8-18
 * Time: 上午9:59
 */

namespace app\models;

use PDO;

use Yii;
use yii\db\Command;


class ResourceActionLogsModel
{
    private $_id;
    private $_user_id;
    private $_user_name;
    private $_resource_type;
    private $_resource_id;
    private $_action_id;
    private $_action_name;
    private $_update_value;
    private $_content;
    private $_created_at;
    private $_cloumn_str = 'id,user_id,user_name,resource_type,resource_id,action_id,action_name,content,created_at';


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
     * @param mixed $action_name
     */
    public function setActionName($action_name)
    {
        $this->_action_name = $action_name;
    }

    /**
     * @return mixed
     */
    public function getActionName()
    {
        return $this->_action_name;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->_content = $content;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->_content;
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
     * @param mixed $resource_id
     */
    public function setResourceId($resource_id)
    {
        $this->_resource_id = $resource_id;
    }

    /**
     * @return mixed
     */
    public function getResourceId()
    {
        return $this->_resource_id;
    }

    /**
     * @param mixed $resource_type
     */
    public function setResourceType($resource_type)
    {
        $this->_resource_type = $resource_type;
    }

    /**
     * @return mixed
     */
    public function getResourceType()
    {
        return $this->_resource_type;
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
    public function getUserId()
    {
        return $this->_user_id;
    }

    /**
     * @param mixed $user_name
     */
    public function setUserName($user_name)
    {
        $this->_user_name = $user_name;
    }

    /**
     * @return mixed
     */
    public function getUserName()
    {
        return $this->_user_name;
    }

    /**
     * @param mixed $update_value
     */
    public function setUpdateValue($update_value)
    {
        $this->_update_value = $update_value;
    }

    /**
     * @return mixed
     */
    public function getUpdateValue()
    {
        return $this->_update_value;
    }

    public function getModelDict(){
        return ['user_id'=>$this->getUserId(),
            'user_name'=>$this->getUserName(),
            'resource_type'=>$this->getResourceType(),
            'resource_id'=>$this->getResourceId(),
            'action_id'=>$this->getActionId(),
            'action_name'=>$this->getActionName(),
            'update_value'=>$this->getUpdateValue(),
            'content'=>$this->getContent(),
            'created_at'=>$this->getCreatedAt()];
    }



    /**
     * 批量创建
     * @return mixed
     */
    public function createBatch()
    {
        //定义SQL
        $sql = 'INSERT INTO resource_action_logs (user_id,user_name,resource_type,resource_id,action_id,action_name,
        content,created_at) VALUES (:user_id,:user_name,:resource_type,:resource_id,:action_id,:action_name,:content,:created_at)';

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
        $command->bindParam(':user_name', $this->_user_name, PDO::PARAM_STR);
        $command->bindParam(':resource_type', $this->_resource_type, PDO::PARAM_INT);
        $command->bindParam(':resource_id', $this->_resource_id, PDO::PARAM_INT);
        $command->bindParam(':action_id', $this->_action_id, PDO::PARAM_INT);
        $command->bindParam(':action_name', $this->_action_name, PDO::PARAM_STR);
        $command->bindParam(':content', $this->_content, PDO::PARAM_STR);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);

        return $command->execute();
    }

    public function searchResourceActionLogs($resource_type, $resource_id){
        if (empty($resource_type) || empty($resource_id)){
            return null;
        }

        $connection = Yii::$app->db;
        $sql = 'SELECT '.$this->_cloumn_str.' FROM resource_action_logs WHERE resource_type=:resource_type AND resource_id=:resource_id ORDER BY id DESC ';
        $command = $connection->createCommand($sql);
        $command->bindParam(':resource_type', $resource_type, PDO::PARAM_INT);
        $command->bindParam(':resource_id', $resource_id, PDO::PARAM_INT);
        $res = $command->queryAll();
        return $res;
    }

    /**
     * 新增
     * @return int
     */
    public function createAttribute()
    {
        $connection = Yii::$app->db;

        $sql = 'INSERT INTO resource_action_logs (user_id,user_name,resource_type,resource_id,action_name,update_value,content,
        created_at) VALUES (:user_id,:user_name,:resource_type,:resource_id,:action_name,:update_value,:content,:created_at)';
        $command = $connection->createCommand($sql);

        $command->bindParam(':user_id', $this->_user_id, PDO::PARAM_INT);
        $command->bindParam(':user_name', $this->_user_name, PDO::PARAM_STR);
        $command->bindParam(':resource_type', $this->_resource_type, PDO::PARAM_INT);
        $command->bindParam(':resource_id', $this->_resource_id, PDO::PARAM_INT);
        $command->bindParam(':action_name', $this->_action_name, PDO::PARAM_STR);
        $command->bindParam(':update_value', $this->_update_value, PDO::PARAM_STR);
        $command->bindParam(':content', $this->_content, PDO::PARAM_STR);
        $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);

        return $command->execute();
    }
}