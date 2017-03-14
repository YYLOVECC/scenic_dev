<?php
namespace app\services\super;

use app\models\OrderInfoModel;
use app\security\StoreClient;
use app\util\ArrayUtil;
use app\util\GearmanClientUtils;
use Exception;
use PDOException;
use Yii;

use app\components\UserIdentity;
use app\models\SystemActionLogsModel;
use app\models\ResourceActionLogsModel;
use app\util\ConstantConfig;

class CLogService
{
    /**
     * 保存系统日志
     * @param SystemActionLogsModel $model
     * @return bool
     */
    public function saveSystemActionLogs(SystemActionLogsModel $model)
    {
        $model->setCreatedAt(Yii::$app->params['current_time']);
        $model->assembledContent();
        try {
            $model->create();
        } catch (PDOException $e) {
            throw $e;
        }

        return true;
    }


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
     * 保存属性修改内容日志
     * @param $resource_type
     * @param $resource_id
     * @param $action_id
     * @param $action_name
     * @param string $content
     * @param string $edit_field
     * @param string $old_value
     * @param string $edit_value
     * @return bool
     * @throws Exception
     */
    public function saveAttributeActionLogs($resource_type, $resource_id, $action_id, $action_name, $content="",
                                            $edit_field="", $old_value="", $edit_value="")
    {
        if(empty($resource_type) or empty($resource_id) or empty($action_name)){
            return false;
        }
        $user = UserIdentity::getUserInfo();
        //传入基本信息
        $attribute_action_logs = new ResourceActionLogsModel();
        $attribute_action_logs->setUserId(ArrayUtil::getVal($user, "id", 0));
        $attribute_action_logs->setUserName(ArrayUtil::getVal($user, "name", ""));
        $attribute_action_logs->setResourceType($resource_type);
        $attribute_action_logs->setResourceId($resource_id);
        $attribute_action_logs->setActionId($action_id);
        $attribute_action_logs->setActionName($action_name);
        $attribute_action_logs->setContent($content);
        $update_value = "";
        if(!empty($edit_field)){
            $update_value = $edit_field."," . $old_value . "," . $edit_value;
        }
        $attribute_action_logs->setUpdateValue($update_value);
        $attribute_action_logs->setCreatedAt(time());

        $conn = Yii::$app->db;
        try{
            $attribute_action_logs->createAttribute();
            $a_log = $attribute_action_logs->getModelDict();
            $a_log['id'] = $conn->getLastInsertID();
            if((int)$resource_type == ConstantConfig::RESOURCE_ORDER){
                //同步日志至进销存系统
                $store_client = new GearmanClientUtils();
                $store_client->syncResourceLog([$a_log]);

                //同步日志至PPC
                $this->_rsyncResourceActionLogs2PPC([$a_log]);
            }
            return true;
        }catch (Exception $e){
            throw $e;
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

    /**
     * 同步日志至PPC
     * @param $log_list
     * @return array|mixed
     */
    private function _rsyncResourceActionLogs2PPC($log_list)
    {
        if(empty($log_list)){
            return ['success'=>false, 'msg'=>'日志同步至PPC，参数传递失败'];
        }
        $gearman_client_utils = new GearmanClientUtils();
        $result = $gearman_client_utils->syncResourceLogs2PPC($log_list);
        return $result;
    }

}