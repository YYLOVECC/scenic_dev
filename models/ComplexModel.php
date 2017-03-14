<?php
/**
 * 两表及以上复杂sql
 */
namespace app\models;

use Yii;

use app\util\ConstantConfig;

class ComplexModel
{

    /**
     * 条件获取角色用户总数
     * @param $role_id
     * @param $params
     * @return mixed
     */
    public function countSearchRoleUsers($role_id, $params)
    {
        //占位符数组
        $data=[];

        $sql = "SELECT count(*) as total FROM user_roles AS a INNER JOIN admin_users b ON a.user_id = b.id
        WHERE a.status=0 AND b.status=0 AND a.role_id=".$role_id;

        //拼装sql条件
        if(!empty($params)){
            if($params['user_name']){
                $sql .= " AND instr(b.name, :user_name)";
                $data[':user_name'] = '%' . $params['user_name'] . '%';
            }
            if($params['user_state']!=ConstantConfig::ENABLE_ALL){
                $sql .= " AND b.is_enable=:is_enable";
                $data[':is_enable'] = '%' . $params['user_state'] . '%';

            }
        }

        //获得数据库连接
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryOne();
        return $result['total'];
    }

    /**
     * 条件检索角色用户列表
     * @param $role_id
     * @param $params
     * @param int $start
     * @param int $page_size
     * @param string $ordinal_str
     * @param string $ordinal_type
     * @return array
     */
    public function searchRoleUsers($role_id, $params, $start=0, $page_size=20, $ordinal_str='id', $ordinal_type='DESC')
    {
        //占位符数组
        $data=[];

        $sql = "SELECT a.* , b.name AS user_name, b.is_enable, b.created_at AS user_created_at FROM user_roles
AS a INNER JOIN admin_users b ON a.user_id = b.id WHERE a.status=0 AND b.status=0 AND a.role_id=".$role_id;

        //拼装sql条件
        if(!empty($params)){
            if($params['user_name']){
                $sql .= " AND instr(b.name, '".$params['user_name']."')";
                $data[':user_name'] = '%' . $params['user_name'] . '%';
            }
            if($params['user_state']!=ConstantConfig::ENABLE_ALL){
                $sql .= " AND b.is_enable=".intval($params['user_state'])."";
                $data[':is_enable'] = $params['user_state'];
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
        $command->bindValues($data);
        $result = $command->queryAll();
        return $result;
    }
}