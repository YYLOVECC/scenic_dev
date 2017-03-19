<?php
namespace app\services\super;

use app\util\ArrayUtil;
use PDOException;
use Yii;

use app\components\UserIdentity;
use app\models\ResourceActionLogsModel;
use app\util\ConstantConfig;

class CLogService
{
    /**
     * 保存资源日志信息
     * @param $ids
     * @param $resource_type
     * @param $action_id
     * @param $action_name
     */
    public function saveResourceActionLogs($ids, $resource_type, $action_id, $action_name)
    {

        $user = UserIdentity::getUserInfo();

        //组合日志数据
        $resource_action_logs = new ResourceActionLogsModel();
        $resource_action_logs->setUserId(ArrayUtil::getVal($user, "id", 0));
        $resource_action_logs->setUserName(ArrayUtil::getVal($user, "name", ""));
        $resource_action_logs->setResourceType($resource_type);
        $resource_action_logs->setActionId($action_id);
        $resource_action_logs->setActionName($action_name);
        $resource_action_logs->setCreatedAt(Yii::$app->params['current_time']);

        //批量插入
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $command = $resource_action_logs->createBatch();
            if(!is_array($ids)){
                $ids = [$ids];
            }
            $r_list = [];
            foreach ($ids as $resource_id) {
                $resource_action_logs->setResourceId($resource_id);
                $resource_action_logs->setContent("");
                $resource_action_logs->setUpdateValue("");

                $resource_action_logs->createBatchExecute($command);
                $resource_logs = $resource_action_logs->getModelDict();
                $resource_logs['id'] = $connection->getLastInsertID();
                array_push($r_list, $resource_logs);
            }
            $transaction->commit();
        } catch (PDOException $e) {
            $transaction->rollBack();
        }
    }

    /**
     * 查询资源的日志信息
     * @param $resource_type
     * @param $resource_id
     * @return array
     */
    public function searchResourceActionLogs($resource_type, $resource_id)
    {
        if (empty($resource_type) || empty($resource_id)) {
            return ['success' => false, 'msg' => '参数传递错误'];
        }
        if (!in_array($resource_type, ConstantConfig::allResourceType())) {
            return ['success' => false, 'msg' => '请求资源参数错误参数传递错误'];
        }

        $resource_action_log_model = new ResourceActionLogsModel();
        $logs = $resource_action_log_model->searchResourceActionLogs($resource_type, $resource_id);
        if (empty($logs)) {
            return ['success' => false, 'msg' => '没有该资源的日志'];
        }
        foreach ($logs as &$log) {
            $log['created_at_str'] = date('Y/m/d G:i:s', $log['created_at']);
        }

        return ['success' => true, 'data' => $logs];

    }
}