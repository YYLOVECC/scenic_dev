<?php
namespace app\services\func;

use app\models\InnerMarksModel;
use app\util\ConstantConfig;
use app\util\GearmanClientUtils;
use Yii;
use yii\base\Exception;

class InnerMarkService
{
    /**
     * 保存内部标签
     * @param $data
     * @return array
     */
    public function saveInnerMark($data) {

        // 如果数据为空，返回false
        if (empty($data)) {
            return ["success" => false, "msg" => '参数传递错误'];
        }

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            //新增内部标签
            $inner_mark_model = new InnerMarksModel();
            $inner_mark_model->setUserId($data['user_id']);
            $inner_mark_model->setUserName($data['user_name']);
            $inner_mark_model->setResourceType($data['resource_type']);
            $inner_mark_model->setResourceId($data['resource_id']);
            $inner_mark_model->setContent($data['content']);
            $inner_mark_model->setOuterCallType($data['outer_call_type']);
            $inner_mark_model->setOuterCallReason($data['outer_call_reason']);
            $inner_mark_model->setCreatedAt(Yii::$app->params['current_time']);
            $inner_mark_model->saveInnerMarks();

            //同步内部标签至进销存系统
            $inner_mark = $inner_mark_model->getModelDict();
            $gearman_client_utils = new GearmanClientUtils();
            $res = $gearman_client_utils->syncInnerMarks2Psi([$inner_mark]);
//            var_dump($res);exit;
            if (!empty($res)) {
                if (!$res['success']) {
                    throw new Exception($res['msg']);
                }
            } else {
                throw new Exception('同步内部标签失败：没有获取到返回参数');
            }

            $transaction->commit();
            //同步内部标签至PPC
            if((int)$data['resource_type'] == ConstantConfig::RESOURCE_ORDER){
                $gearman_client_utils->syncInnerMarks2PPC([$inner_mark]);
            }

            return ["success" => true, "msg" => "添加成功"];
        }catch (Exception $e){
            $transaction->rollBack();
            return ["success" => false, "msg" => '添加失败 ' . $e->getMessage()];
        }
    }

    /**
     * 根据资源获取内部标签
     * @param $resource_type
     * @param $resource_id
     * @return array
     */
    public function getInnerMark($resource_type, $resource_id) {
        if (empty($resource_type) || empty($resource_id)) {
            return [];
        }

        $inner_mark_model = new InnerMarksModel();
        $inner_mark_model->setResourceType($resource_type);
        $inner_mark_model->setResourceId($resource_id);
        $result = $inner_mark_model->getAllInnerMarksByResource();
        if (!empty($result)) {
            foreach($result as &$item) {
                $item['created_at_str'] = !empty($item['created_at'])?date('Y/m/d H:i:s', intval($item['created_at'])):'';
            }
        }
        return $result;
    }
}
