<?php
/**
 * Created by PhpStorm.
 * User: yangyue
 * Date: 16-8-18
 * Time: 上午11:32
 */

namespace app\services\order;

use app\models\OrderChannelModel;
use app\util\ArrayUtil;
use app\util\GearmanClientUtils;
use app\util\RedisUtil;
use Yii;
use yii\web\HttpException;

class COrderChannelService
{


    /**
     * 获取状态正常的订购渠道
     * @return array|mixed|null
     */
    public function getNormalOrderChannelsDict()
    {
        $order_channel_normal_cache_key = 'order_channels_normal';
        $res = RedisUtil::get($order_channel_normal_cache_key);
        if (empty($res)) {
            $order_channel_model = new OrderChannelModel();
            $res = $order_channel_model->getNormalOrderChannelList();
            if (!empty($res)) {
                $res = ArrayUtil::listToDict($res, 'id', 'channel_name');
                RedisUtil::set($order_channel_normal_cache_key, json_encode($res), null, 86400);
            }
        } else {
            $res = json_decode($res, true);
        }
        return $res;
    }
    /**
     * @return array|mixed|null
     */
    public function getOrderChannelsDict()
    {
        $order_channel_cache_key = 'order_channels';
        $res = RedisUtil::get($order_channel_cache_key);
        if (empty($res)) {
            $order_channel_model = new OrderChannelModel();
            $res = $order_channel_model->getOrderChannelList();
            if (!empty($res)) {
                $res = ArrayUtil::listToDict($res, 'id', 'channel_name');
                RedisUtil::set($order_channel_cache_key, json_encode($res), null, 86400);
            }
        } else {
            $res = json_decode($res, true);
        }
        return $res;
    }

    public function validate($data)
    {
        $order_channel_model = new  OrderChannelModel();
        $result = $order_channel_model->validate($data);
        $res=intval($result[0]['total']);
        return $res;
    }

    public function save($data)
    {
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        $order_channel_model = new  OrderChannelModel();
        try {
            $order_channel_model->setProjectType($data["project_type"]);
            $order_channel_model->setChannelName($data["channel_name"]);
            $order_channel_model->setCreatedAt($data["created_at"]);
            $insert_id = $order_channel_model->save();
            $transaction->commit();

            // 清空之前的缓存
            $order_channel_cache_key = 'order_channels';
            $order_channel_normal_cache_key = 'order_channels_normal';
            RedisUtil::del($order_channel_cache_key);
            RedisUtil::del($order_channel_normal_cache_key);
            $channel_data = ['id' => $insert_id, 'name'=>$data["channel_name"],
                'created_at'=>$data["created_at"], 'updated_at'=>$data["created_at"]];
            $sync_res = $this->sync2PPC([$channel_data]);
            if (!$sync_res['success']) {
                return ['success' => true, 'msg' => '渠道保存成功,但同步ppc失败,请重新编辑该数据,进行同步'];
            } else {
                return ['success' => true, 'msg' => '渠道保存成功'];
            }
        } catch (\HttpException $e) {
//            echo $e->getMessage();
            return  ['success' => false, 'msg' => '渠道保存失败'];
        }
    }

    /**
     * 更新订购渠道
     * @param $data
     * @return array
     */

    public function update($data)
    {
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        $order_channel_model = new  OrderChannelModel();
        try {
            $order_channel_model->setId($data["id"]);
            $order_channel_model->setChannelName($data["channel_name"]);
            $order_channel_model->setUpdatedAt($data["updated_at"]);
            $order_channel_model ->update();
            $transaction->commit();
            // 清空之前的缓存
            $order_channel_cache_key = 'order_channels';
            $order_channel_normal_cache_key = 'order_channels_normal';
            RedisUtil::del($order_channel_cache_key);
            RedisUtil::del($order_channel_normal_cache_key);

            $channel_data = ['id' => $data["id"], 'name'=>$data["channel_name"],
                'updated_at'=>$data["updated_at"], 'created_at'=>0];
            $sync_res = $this->sync2PPC([$channel_data]);
//            $sync_res = ['success'=>true];
            if (!$sync_res['success']) {
                return ['success' => true, 'msg' => '渠道修改成功,但同步ppc失败,请重新编辑该数据,进行同步:'.$sync_res['msg']];
            } else {
                return ['success' => true, 'msg' => '渠道修改成功'];
            }
        } catch (\HttpException $e) {
//            echo $e->getMessage();
            return  ['success' => false, 'msg' => '渠道修改失败,'];
        }
    }

    public function searchChannelList($query, $start, $page_size)
    {
        $model = new OrderChannelModel();
        $count = $model ->countChannel($query);
        $ordinal_channel = $model->getOrderChannelList($query, $start, $page_size);
        return ['success' => true, 'count' => $count, 'data' => $ordinal_channel];
    }

    public function getChannelsByProjectType($project_type)
    {
        $res = [];
        $query["project_type"] = intval($project_type);
        $order_channel_model = new  OrderChannelModel();
        $result = $order_channel_model ->getNormalOrderChannelList($query);

        foreach ($result as $key => $value) {
            $res["$key"]["id"] = intval($value["id"]);
            $res["$key"]["channel_name"] = $value["channel_name"];
        }
        return $res;
    }

    private function sync2PPC($params_channel_list)
    {
        if (empty($params_channel_list)) {
            return ['success' => false, 'msg' => '数据同步至PPC,参数提交错误'];
        }
        $gearman_client_utils = new GearmanClientUtils();
        return $gearman_client_utils->syncOrderChannel2PPC($params_channel_list);
    }

    /**
     * 订购渠道状态停启用
     * @param $id
     * @param $state
     * @param $updated_at
     * @return array|bool
     */

    public function updateState($id,$state, $updated_at)
    {
        if(!$id) {
            return false;
        }

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        $order_cnannel_model = new OrderChannelModel();

        try{
            $order_cnannel_model->setId($id);
            $order_cnannel_model->setState($state);
            $order_cnannel_model->setUpdatedAt($updated_at);
            $order_cnannel_model->updateState();
            $transaction->commit();

            // 清空之前的缓存
            $order_channel_cache_key = 'order_channels';
            $order_channel_normal_cache_key = 'order_channels_normal';
            RedisUtil::del($order_channel_cache_key);
            RedisUtil::del($order_channel_normal_cache_key);

            // 同步信息至PPC
            $channel_data = ['id' => $id, 'state'=>$state,
                'updated_at'=>$updated_at];
            $sync_res = $this->sync2PPC([$channel_data]);
//            $sync_res = ['success'=>true];
            if (!$sync_res['success']) {
                return ['success' => true, 'msg' => '成功,但同步ppc失败,请重新编辑该数据,进行同步:'.$sync_res['msg']];
            } else {
                return ['success' => true, 'msg' => '成功'];
            }

        } catch(HttpException $e) {
            $transaction->rollBack();
            return ['success' => false, 'msg' => '失败'];
        }

    }
}
