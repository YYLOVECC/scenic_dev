<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/11
 * Time: 13:57
 */
namespace app\models;
use Yii;
use PDO;
use yii\db\Exception;

class ScenicModel
{
    private $_id;
    private $_user_id;
    private $_name;
    private $_image;
    private $_category;
    private $_info;
    private $_remark;
    private $_place_id;
    private $_country_id;
    private $_hot;
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
    public function getImage()
    {
        return $this->_image;
    }

    /**
     * @param mixed $image
     */
    public function setImage($image)
    {
        $this->_image = $image;
    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->_category;
    }

    /**
     * @param mixed $category
     */
    public function setCategory($category)
    {
        $this->_category = $category;
    }

    /**
     * @return mixed
     */
    public function getInfo()
    {
        return $this->_info;
    }

    /**
     * @param mixed $info
     */
    public function setInfo($info)
    {
        $this->_info = $info;
    }

    /**
     * @return mixed
     */
    public function getRemark()
    {
        return $this->_remark;
    }

    /**
     * @param mixed $remark
     */
    public function setRemark($remark)
    {
        $this->_remark = $remark;
    }

    /**
     * @return mixed
     */
    public function getPlaceId()
    {
        return $this->_place_id;
    }

    /**
     * @param mixed $place_id
     */
    public function setPlaceId($place_id)
    {
        $this->_place_id = $place_id;
    }

    /**
     * @return mixed
     */
    public function getCountryId()
    {
        return $this->_country_id;
    }

    /**
     * @param mixed $country_id
     */
    public function setCountryId($country_id)
    {
        $this->_country_id = $country_id;
    }

    /**
     * @return mixed
     */
    public function getHot()
    {
        return $this->_hot;
    }

    /**
     * @param mixed $hot
     */
    public function setHot($hot)
    {
        $this->_hot = $hot;
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
     * count 订单列表数量
     * @param $query
     * @return mixed
     */
    public function countScenicList($query)
    {
        $connection = Yii::$app->db;

        $sql = "SELECT count(DISTINCT(s.id)) as num FROM scenic s";
        //占位符数组
        $data = [];
        $sql .= " WHERE s.status = 0";

        $c_res = $this->_innerSearchQuery($query);
        $sql .= $c_res['sql'];
        $data = array_merge($data, $c_res['data']);
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryOne();

        return $result['num'];
    }

    /**
     * @param $query
     * @param int $limit
     * @param int $limit_size
     * @param string $ordinal_str
     * @param string $ordinal_type
     * @return int
     * @throws Exception
     */
    public function searchScenicList($query, $limit = 0, $limit_size = 20, $ordinal_str = '', $ordinal_type = '')
    {
        if (empty($ordinal_str)) {
            $ordinal_str = 'created_at';
        }

        if (empty($ordinal_type)) {
            $ordinal_type = 'DESC';
        }

        if (empty($limit)) {
            $limit = 0;
        }

        if (empty($limit_size)) {
            $limit_size = 20;
        }

        //占位符数组
        $data = [];

        $connection = Yii::$app->db;
        $sql = "SELECT s.* FROM scenic s ";
        $sql .= " WHERE s.status=0 ";
        $c_res = $this->_innerSearchQuery($query);
        $sql .= $c_res['sql'];
        $data = array_merge($data, $c_res['data']);
        $sql .= ' ORDER BY s.' . $ordinal_str . ' ' . $ordinal_type;
        $sql .= ' LIMIT ' . $limit . ',' . $limit_size;
        $command = $connection->createCommand($sql);
        $command->bindValues($data);
        $result = $command->queryAll();

        return $result;
    }

    private function _innerSearchQuery($query){
        $sql='';
        $data=[];

        //创建时间
        if ($query['created_at_begin'] > 0) {
            $sql .= ' AND s.created_at >= :created_at_begin';
            $data[':created_at_begin'] = $query['created_at_begin'];
        }
        if ($query['created_at_end'] > 0) {
            $sql .= ' AND s.created_at <= :created_at_end';
            $data[':created_at_end'] = $query['created_at_end'];
        }
//        //景点名称
//        if (array_key_exists('scenic_name', $query) and !empty($query['scenic_name'])) {
//            $sql .= ' AND scenic_name=:scenic_name';
//            $data[':scenic_name'] = $query['scenic_name'];
//        }
//        //经销商名称
//        if (array_key_exists('user_id', $query) and $query['user_id']>0) {
//            $sql .= ' AND user_id=:user_id';
//            $data[':user_id'] = $query['user_id'];
//        }
        return ['sql'=>$sql, 'data'=>$data];
    }


}