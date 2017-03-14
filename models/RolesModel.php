<?php
namespace app\models;

use PDO;

use Yii;
use yii\db\Exception;

use app\util\ConstantConfig;

class RolesModel
{
    private $_id;
    private $_name;
    private $_parent_id;
    private $_description;
    private $_level;
    private $_is_enable;
    private $_status;
    private $_create_user_id;
    private $_create_user_name;
    private $_update_user_id;
    private $_update_user_name;
    private $_created_at;
    private $_updated_at;


    /**
     * @return mixed
     */
    public function getCreateUserId()
    {
        return $this->_create_user_id;
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
    public function getCreateUserName()
    {
        return $this->_create_user_name;
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
    public function getUpdateUserId()
    {
        return $this->_update_user_id;
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
    public function getUpdateUserName()
    {
        return $this->_update_user_name;
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
    public function getParentId()
    {
        return $this->_parent_id;
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
    public function getDescription()
    {
        return $this->_description;
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
    public function getLevel()
    {
        return $this->_level;
    }

    /**
     * @param mixed $level
     */
    public function setLevel($level)
    {
        $this->_level = $level;
    }

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
     * 检测同级中角色名是否已存在
     * @return int
     */
    public function checkRoleExist()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM roles where name=:name and level=:level and status=0";
        $command = $connection->createCommand($sql);
        $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
        $command->bindParam(':level', $this->_level, PDO::PARAM_INT);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 查询有效角色信息
     * @return int
     */
    public function getEnableRoles()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM roles where is_enable=:is_enable and status=0";
        $command = $connection->createCommand($sql);
        $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 根据id查询角色信息
     * @return array|bool
     */
    public function getById()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM roles where id=:id and status=0";
        $command = $connection->createCommand($sql);
        $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
        $result = $command->queryOne();
        return $result;
    }

    /**
     * 批量检索角色信息
     * @param $ids
     * @return array
     */
    public function getByIds($ids)
    {
        if(empty($ids)){
            return [];
        }
        $ids_str = implode(',', $ids);
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM roles where id in (".$ids_str.") and status=0";
        $command = $connection->createCommand($sql);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 根据父角色id查询子角色
     * @return array
     */
    public function getRoleByPid()
    {
        $connection = Yii::$app->db;
        $sql = "SELECT * FROM roles where parent_id=:parent_id and is_enable=:is_enable and status=0";
        $command = $connection->createCommand($sql);
        $command->bindParam(':parent_id', $this->_parent_id, PDO::PARAM_INT);
        $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
        $result = $command->queryAll();
        return $result;
    }

    /**
     * 新增角色
     * @return bool|int
     */
    public function create()
    {
        $connection = Yii::$app->db;
        $sql = "INSERT INTO roles (name, parent_id, description, level, is_enable, create_user_id, create_user_name,
        created_at, updated_at) VALUES (:name, :parent_id, :description, :level, :is_enable, :create_user_id,
        :create_user_name, :created_at, :updated_at)";
        try{
            $command = $connection->createCommand($sql);
            $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
            $command->bindParam(':parent_id', $this->_parent_id, PDO::PARAM_INT);
            $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
            $command->bindParam(':level', $this->_level, PDO::PARAM_INT);
            $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
            $command->bindParam(':create_user_id', $this->_create_user_id, PDO::PARAM_INT);
            $command->bindParam(':create_user_name', $this->_create_user_name, PDO::PARAM_STR);
            $command->bindParam(':created_at', $this->_created_at, PDO::PARAM_INT);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $result = $command->execute();
            return $result;
        }catch (Exception $e){
            return false;
        }
    }

    /**
     * 修改信息
     * @return bool
     */
    public function update(){
        $connection = Yii::$app->db;
        $sql = "UPDATE roles SET name=:name, parent_id=:parent_id, description=:description,level=:level,
        update_user_id=:update_user_id,update_user_name=:update_user_name,updated_at=:updated_at WHERE id=:id";
        try{
            $command = $connection->createCommand($sql);
            $command->bindParam(':id', $this->_id, PDO::PARAM_INT);
            $command->bindParam(':name', $this->_name, PDO::PARAM_STR);
            $command->bindParam(':parent_id', $this->_parent_id, PDO::PARAM_INT);
            $command->bindParam(':description', $this->_description, PDO::PARAM_STR);
            $command->bindParam(':level', $this->_level, PDO::PARAM_INT);
            $command->bindParam(':update_user_id', $this->_update_user_id, PDO::PARAM_INT);
            $command->bindParam(':update_user_name', $this->_update_user_name, PDO::PARAM_STR);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->execute();
            return true;
        }catch (Exception $e){
            return false;
        }

    }

    /**
     * 批量修改角色层级
     * @param $ids
     * @param $inc_level: 改变的层级增量
     * @return bool
     */
    public function updateLevelByIds($ids, $inc_level)
    {
        if(empty($ids)){
            return false;
        }
        $id_str = implode(',', $ids);
        $connection = Yii::$app->db;
        $sql = "UPDATE roles SET level =level+".$inc_level." , update_user_id=:update_user_id,
        update_user_name=:update_user_name,updated_at=:updated_at WHERE id in (".$id_str.")";
        try{

            $command = $connection->createCommand($sql);
            $command->bindParam(':update_user_id', $this->_update_user_id, PDO::PARAM_INT);
            $command->bindParam(':update_user_name', $this->_update_user_name, PDO::PARAM_STR);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->execute();
            return true;
        }catch (Exception $e){
            return false;
        }
    }

    /**
     * 批量修改角色状态
     * @param $ids
     * @return bool
     */
    public function updateStatusByIds($ids)
    {
        if(empty($ids)){
            return false;
        }
        $id_str = implode(',', $ids);
        $connection = Yii::$app->db;
        $sql = "UPDATE roles SET status=:status, update_user_id=:update_user_id, update_user_name=:update_user_name,
 updated_at=:updated_at WHERE id in (".$id_str.")";
        try{

            $command = $connection->createCommand($sql);
            $command->bindParam(':status', $this->_status, PDO::PARAM_INT);
            $command->bindParam(':update_user_id', $this->_update_user_id, PDO::PARAM_INT);
            $command->bindParam(':update_user_name', $this->_update_user_name, PDO::PARAM_STR);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->execute();
            return true;
        }catch (Exception $e){
            return false;
        }
    }

    /**
     * 条件获取角色总数
     * @param $params
     * @return mixed
     */
    public function countSearchRoles($params)
    {
        $sql = "SELECT count(*) as total FROM roles where status=0";
        //拼装sql条件
        if(!empty($params)){
            if($params['role_name']){
                $sql .= " AND instr(name, :role_name)";
            }
            if($params['state']!=ConstantConfig::ENABLE_ALL){
                $sql .= " AND is_enable=:state";
            }
        }
        //获得数据库连接
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        //预处理
        if(!empty($params)){
            if(!empty($params['role_name'])){
                $command->bindParam(':role_name', $params['role_name'], PDO::PARAM_STR);
            }
            if($params['state']!=ConstantConfig::ENABLE_ALL){
                $command->bindParam(':state', $params['state'], PDO::PARAM_INT);
            }
        }
        $result = $command->queryOne();
        return $result['total'];
    }

    /**
     * 条件检索角色信息
     * @param $params
     * @param int $start
     * @param int $page_size
     * @param string $ordinal_str
     * @param string $ordinal_type
     * @return array
     */
    public function searchRoles($params, $start=0, $page_size=20, $ordinal_str='id', $ordinal_type='DESC')
    {
        $sql = "SELECT * FROM roles where status=0";
        //拼装sql条件
        if(!empty($params)){
            if($params['role_name']){
                $sql .= " AND instr(name, :role_name)";
            }
            if($params['state']!=ConstantConfig::ENABLE_ALL){
                $sql .= " AND is_enable=:state";
            }
        }
        if(empty($ordinal_str)){
            $ordinal_str = 'id';
        }
        if(empty($ordinal_type)){
            $ordinal_type = ConstantConfig::ORDINAL_DESC;
        }
        if(empty($start)){
            $start = 0;
        }
        if(empty($page_size)){
            $page_size = 20;
        }
        //排序
        $sql .= ' ORDER BY '.$ordinal_str.' '.$ordinal_type;
        //分页
        $sql .= ' LIMIT '.$start.', '.$page_size;
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        //预处理
        if(!empty($params)){
            if(!empty($params['role_name'])){
                $command->bindParam(':role_name', $params['role_name'], PDO::PARAM_STR);
            }
            if($params['state']!=ConstantConfig::ENABLE_ALL){
                $command->bindParam(':state', $params['state'], PDO::PARAM_INT);
            }
        }

        $result = $command->queryAll();
        return $result;
    }

    /**
     * 批量修改角色停启用状态
     * @param $ids
     * @return bool
     */
    public function updateStateByIds($ids)
    {
        if(empty($ids)){
            return false;
        }
        $id_str = implode(',', $ids);
        $connection = Yii::$app->db;
        $sql = "UPDATE roles SET is_enable=:is_enable, update_user_id=:update_user_id,
        update_user_name=:update_user_name, updated_at=:updated_at WHERE id in (".$id_str.")";
        try{

            $command = $connection->createCommand($sql);
            $command->bindParam(':is_enable', $this->_is_enable, PDO::PARAM_INT);
            $command->bindParam(':update_user_id', $this->_update_user_id, PDO::PARAM_INT);
            $command->bindParam(':update_user_name', $this->_update_user_name, PDO::PARAM_STR);
            $command->bindParam(':updated_at', $this->_updated_at, PDO::PARAM_INT);
            $command->execute();
            return true;
        }catch (Exception $e){
            return false;
        }
    }

}