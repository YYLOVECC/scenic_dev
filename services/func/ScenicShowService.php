<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/11
 * Time: 16:14
 */
namespace app\services\func;
use app\models\AdminUsersModel;
use app\models\ScenicModel;
use app\util\ConstantConfig;
use Yii;
use yii\base\Exception;

class ScenicShowService
{
    /**
     * 查询订单数据
     * @param $params
     * @param $ordinal_str
     * @param $ordinal_type
     * @param int $limit
     * @param int $limit_size
     * @return array
     */
    public function searchScenicList($params, $ordinal_str, $ordinal_type, $limit = 0, $limit_size = 20)
    {
        //db查询
        $scenic_info_model = new ScenicModel();
        $count = $scenic_info_model->countScenicList($params);
        $scenic_list = $scenic_info_model->searchScenicList($params, $limit, $limit_size, $ordinal_str, $ordinal_type);
        //格式化景区数据
        $scenic_list = $this->_formatOrderList($scenic_list);
        return ['success' => true, 'count' => $count, 'scenic_data' => $scenic_list];
    }

    /**
     * 格式化数据
     * @param $scenic_list
     * @return array
     */
    public function _formatOrderList($scenic_list){
        if (empty($scenic_list)) {
            return [];
        }
        foreach ($scenic_list as &$value) {
            $value['status_str'] = $value['status'] == 1? "上架":"下架";
            $value['hot'] = $value['hot'] == 1? "热":"一般";
        }
        return $scenic_list;
    }

    /**
     * 获取景区信息
     * @return array
     */
    public function getAllScenicInfo() {
        $scenic_model = new ScenicModel();
        $scenic_name = $scenic_model->getAllScenicInfo();
        return $scenic_name;
    }

    /**
     * 下架
     * @param $scenic_ids
     * @return array
     */
    public function downScenic($scenic_ids)
    {
        if (empty($scenic_ids)) {
            return ['success'=>false, 'msg'=>'参数传递不完整'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $admin_user_model = new ScenicModel();
            $admin_user_model->setStatus(ConstantConfig::STATUS_DOWN);
            $result = $admin_user_model->downScenic($scenic_ids);
            if (!$result) {
                return ['success'=>false, 'msg'=>'状态更新失败'];
            }
            $transaction->commit();
            return ['success'=> true, 'msg'=>'下架成功'];
        } catch(Exception $e) {
            $transaction->rollBack();
            return ['success'=> false, 'msg'=>'下架失败'];
        }
    }
    /**
     *上架
     * @param $scenic_ids
     * @return array
     */
    public function upScenic($scenic_ids)
    {
        if (empty($scenic_ids)) {
            return ['success'=>false, 'msg'=>'参数传递不完整'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $scenic_model = new ScenicModel();
            $scenic_model->setStatus(ConstantConfig::STATUS_UP);
            $result = $scenic_model->upScenic($scenic_ids);
            if (!$result) {
                return ['success'=>false, 'msg'=>'状态更新失败'];
            }
            $transaction->commit();
            return ['success'=> true, 'msg'=>'上架成功'];
        } catch(Exception $e) {
            $transaction->rollBack();
            return ['success'=> false, 'msg'=>'上架失败'];
        }
    }
    /**
     * 下架
     * @param $scenic_ids
     * @return array
     */
    public function forceDownScenic($scenic_ids)
    {
        if (empty($scenic_ids)) {
            return ['success'=>false, 'msg'=>'参数传递不完整'];
        }
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try{
            $admin_user_model = new ScenicModel();
            $admin_user_model->setStatus(ConstantConfig::STATUS_FORCE_DOWN);
            $result = $admin_user_model->forceDownScenic($scenic_ids);
            if (!$result) {
                return ['success'=>false, 'msg'=>'状态更新失败'];
            }
            $transaction->commit();
            return ['success'=> true, 'msg'=>'下架成功'];
        } catch(Exception $e) {
            $transaction->rollBack();
            return ['success'=> false, 'msg'=>'下架失败'];
        }
    }

    public function getValidScenicInfo($user_info) {
        if (empty($user_info)) {
            return ['success'=>false, 'msg'=>'传递参数不完整'];
        }
        $user_name = $user_info['name'];
        $scenic_model = new ScenicModel();
        $valid_scenic = $scenic_model->getValidScenic($user_name);
        if (!empty($valid_scenic)) {
            return $valid_scenic;
        }
    }

}
