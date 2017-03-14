<?php
/**
 * Created by PhpStorm.
 * User: teresa
 * Date: 8/31/15
 * Time: 11:55 AM
 */

namespace app\services\func;

use app\models\BaseMerchandiseInfoModel;
use app\models\BaseMerchandiseSpecificationsModel;
use app\models\MerchandiseInfoModel;
use app\models\MerchandiseSpecificationsModel;
use app\models\PlatformRequestLogModel;
use app\models\StoresModel;
use app\services\store\CStoreService;
use app\util\ArrayUtil;
use app\util\ConstantConfig;
use app\util\GearmanClientUtils;
use app\util\Tools;
use app\util\ExcelUtil;
use Yii;

use app\security\StoreClient;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class StoreService
{
    public function syncStore($store_info)
    {
        //访问方法
        $method = "update_store";
        $now_time = Yii::$app->params['current_time'];

        //组装参数
        $check_data = [];
        $check_data['app_key'] = Yii::$app->params['app_key'];
        $check_data['session_key'] = Yii::$app->params['session_key'];
        $check_data['method'] = $method;
        $check_data['now_time'] = $now_time;

        //生成签名
        $secret_key = Yii::$app->params['secret_key'];
        $sign = Tools::createSign($secret_key, $check_data);

        //提交请求
        $from['data'] = base64_encode($compressed = gzcompress(json_encode($store_info), 9));
        $url = "http://order.api.hongju.cc/update_store?sign={$sign}&method={$method}&now_time={$now_time}";
        $response = Tools::requestPost($url, $from);

        //请求结果
        $res = json_decode($response,true);
        if($res['status'] == true){
            return ['success' => true, 'msg' => '店铺同步成功'];
        }else{
            return ['success' => false, 'msg' => '店铺同步失败'];
        }
    }

    /**
     * 第三方同步订单
     * @param $store_id
     * @param $platform_sns
     * @return array
     */
    public function synOrder($store_id, $platform_sns=null)
    {
        if (empty($store_id)) {
            return ['success' => false, 'msg' => '参数传递错误：store_id'];
        }

        $platform_request_log_model = new PlatformRequestLogModel();
        $is_time_change = false;
        $curr_time = time();
        $is_req_log = true;
        //拼装同步参数
        $sync_params = ['store_id' => $store_id];
        if(isset($platform_sns) and !empty($platform_sns)){
            $sync_params['platform_sns'] = $platform_sns;
        }else{
            $order_last_req_at = $platform_request_log_model->getOrderLastReqAt($store_id);
            if (!empty($order_last_req_at)) {
                $req_limit_time = 10 * 60;
                if ($curr_time - $order_last_req_at < $req_limit_time) {
                    $margin_time = round(($req_limit_time - ($curr_time - $order_last_req_at)) / 60);
                    return ['success' => false, 'msg' => '请求太频繁了，请' . $margin_time . '分钟后再试'];
                }
            } else {
                $is_req_log = false;
            }
            //获取最近同步时间
            $last_time = $this->_getStoreLastSynOrderTime($store_id);
            $start_created = !(empty($last_time)) ? intval($last_time) + 1 : 0;
            $end_created = $curr_time;
            $sync_params['time_flag'] = $start_created . '-' . $end_created;
//            $sync_params['end_at'] = $end_created;
            $is_time_change = true;
        }

        //同步worker
        $gearman_client_utils = new GearmanClientUtils();
        $result = $gearman_client_utils->syncStoresOrders($sync_params);
        if (!$result['success']) {
            return ['success' => false, 'msg' => '订单同步失败 ' . $result['msg']];
        }

        //维护订单同步时间
        if($is_time_change) {
            $stores_model = new StoresModel();
            $stores_model->setId($store_id);
            $stores_model->setOrderAt($curr_time);
            $stores_model->updateOrderAtById();

            if (!$is_req_log) {
                $platform_request_log_model->create(['store_id' => $store_id, 'req_type' => 1, 'req_at' => $curr_time, 'created_at' => $curr_time]);
            } else {
                $platform_request_log_model->updateByStoreId($store_id, $curr_time);
            }
        }
        return ['success' => true, 'msg' => '订单同步任务提交成功'];

    }

    /**
     * 第三方同步商品
     * @param $store_id
     * @return array
     */
    public function synMerchandise($store_id)
    {
        if (empty($store_id)) {
            return ['success' => false, 'msg' => '参数传递错误：store_id'];
        }
        //获取最近同步时间
        $last_time = $this->_getStoreLastSynMerchandiseTime($store_id);
        $start_updated = !(empty($last_time)) ? intval($last_time) + 1 : 0;
        $end_updated = Yii::$app->params['current_time'];
        //同步worker
        $store_client = new StoreClient();
        $result = $store_client->synStoreMerchandises($store_id, $start_updated, $end_updated);
        if (!$result['success']) {
            return ['success' => false, 'msg' => '商品同步失败' . $result['msg']];
        }

        //维护商品同步时间
        $stores_model = new StoresModel();
        $stores_model->setId($store_id);
        $stores_model->setMerchandiseAt($end_updated);
        $stores_model->updateMerchandiseAtById();
        return ['success' => true, 'msg' => '商品同步成功'];

    }

    /**
     * 获取第三方店铺最近创建订单时间
     * @param $store_id
     * @return array|null
     */
    private function _getStoreLastSynOrderTime($store_id)
    {
        if(empty($store_id)){
            return 0;
        }
        $c_store_service = new CStoreService();
        $store_info = $c_store_service->getById($store_id);
        if(empty($store_info)){
            return 0;
        }
        return (int)$store_info['order_at'];
    }

    /**
     * 查询店铺最近同步店铺时间
     * @param $store_id
     * @return array|null
     */
    private function _getStoreLastSynMerchandiseTime($store_id)
    {
        if(empty($store_id)){
            return 0;
        }
        $c_store_service = new CStoreService();
        $store_info = $c_store_service->getById($store_id);
        if(empty($store_info)){
            return 0;
        }
        return (int)$store_info['merchandise_at'];
    }

    public function findById($id)
    {
        $dao = new StoresModel();
        $dao->setId($id);
        return $dao->getById();
    }


    /**
     * 导入店铺商品数据处理
     * @param $file_path
     * @param $platform_type
     * @param $store_id
     * @return array|null
     */
    public function processImportMerchandises($file_path, $platform_type, $store_id){
        // 解析导入信息
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        $the_file_path = $doc_root.$file_path;
        $excel_util = new ExcelUtil();
        $process_data_list = $excel_util->formatExcelData($the_file_path, ConstantConfig::EXCEL_TEMPLATE_FILENAME_STORE_MERCHANDISE, ConstantConfig::EXCEL_TEMPLATE_DICT_STORE_MERCHANDISE);
        if (empty($process_data_list)) {
            return ['success' => false, 'msg' => '数据解析失败'];
        }

        $process_data_list_count = count($process_data_list);
        $r_num = ceil($process_data_list_count / 100);
        $base_merchandise_info_model = new BaseMerchandiseInfoModel();
        $base_merchandise_specification_model = new BaseMerchandiseSpecificationsModel();
        $all_diff_sn = [];
        $all_diff_code = [];
        $all_empty_sn_num = 0;
        for($j=0;$j<$r_num;$j++){
            //将excel中的sn ,code 循环获取到
            $j_sn = [];
            $j_code = [];
            $c_all_process_data = [];
            for($n=0;$n<100;$n++){
                $index = $j*100 + $n;
                $c_item = $process_data_list[$index];
                $c_sn = trim(ArrayUtil::getVal($c_item, 'merchandise_sn', ''));
                if(empty($c_sn)){
                    continue;
                }
                if(!in_array($c_sn, $j_sn)){
                    $j_sn[] = $c_sn;
                }
                $c_code = trim(ArrayUtil::getVal($c_item, 'specification_code', ''));
                if(!empty($c_code)){
                    if(!in_array($c_code, $j_code)){
                        $j_code[] = $c_code;
                    }
                }
                $c_all_process_data[] = $c_item;
            }

            //判断获取到的sn ,code 是否都存在于基础资料中
            $base_sn = [];
            $store_info = $this->findById($store_id);
            $base_m_list = $base_merchandise_info_model->getMerchandiseBySnList($j_sn, $store_info['project_type']);
            $base_m_dict = [];
            foreach($base_m_list as $base_m_item){
                $base_sn[] = $base_m_item['merchandise_sn'];
                $base_m_dict[$base_m_item['merchandise_sn']] = $base_m_item;
            }
            $diff_sn = array_diff($j_sn, $base_sn); //基础资料中不存在的sn
            array_merge($all_diff_sn, $diff_sn);

            //获取specification资料数据
            $base_code = [];
            $base_spe_list = $base_merchandise_specification_model->getMerchandiseSpecificationsByCodeList($j_code, $store_info['project_type']);
            $base_spe_dict = [];
            foreach($base_spe_list as $base_spe_item){
                $base_code[] = $base_spe_item['merchandise_specification_code'];
                $base_spe_dict[$base_spe_item['merchandise_specification_code']] = $base_spe_item;
            }
            $diff_code = array_diff($j_code, $base_code);//基础资料中不存在的code
            $all_diff_code = array_merge($all_diff_code, $diff_code);

            $need_process_data = [];
            foreach($c_all_process_data as $c_all_item){
                $t_sn = trim(ArrayUtil::getVal($c_all_item,'merchandise_sn'));
                $t_code = trim(ArrayUtil::getVal($c_all_item,'specification_code'));
                if(empty($t_sn)){
                    $all_empty_sn_num ++;
                    continue;
                }
                if(empty($t_code)){
                    if(!in_array($t_sn, $diff_sn)){
                        $need_process_data[] = $c_all_item;
                    }
                }else{
                    if(!in_array($t_sn, $diff_sn) && !in_array($t_code, $diff_code)){
                        $base_spe_info = ArrayUtil::getVal($base_spe_dict, $t_code,'');
                        if(!empty($base_spe_info)){
                            $c_spe_info_sn = ArrayUtil::getVal($base_spe_info, 'merchandise_sn', '');
                            if ($c_spe_info_sn == $t_sn){
                                $need_process_data[] = $c_all_item;
                            }else{
                                $all_diff_code[] = $t_code;
                            }
                        }
                    }
                }
            }
            if(empty($need_process_data)){
                continue;
            }

            $is_sku_error_data = [];
            $connection = Yii::$app->db;
            $transaction = $connection->beginTransaction();
            try{
                //插入已经验证过存在的商品
                $res = $this->InsertStoreMerchandise($need_process_data, $store_id, $platform_type, $base_m_dict, $base_spe_dict);
                if(!$res['success']){
                    $transaction->rollBack();
                }else{
                    $transaction->commit();
                    $is_sku_error_data = $res['is_sku_error_data'];
                }
            }catch(Exception $e){
                $transaction->rollBack();
            }
        }
        return ['success'=>true, 'msg'=>'导入完成', 'error_sn'=>$all_diff_sn, 'error_code'=>$all_diff_code,
            'empty_sn_num'=>$all_empty_sn_num, 'is_sku_error_data'=>$is_sku_error_data];
    }

    function InsertStoreMerchandise($need_process_data, $store_id, $platform_type, $base_merchandise_dict, $base_merchandise_specification_dict){
        if(empty($need_process_data) || empty($store_id) || empty($platform_type) || empty($base_merchandise_dict)){
            return ['success'=> false, ];
        }

        $all_sn = [];
        $all_code = [];
        foreach($need_process_data as $item){
            $c_sn = ArrayUtil::getVal($item,'merchandise_sn');
            $c_code = ArrayUtil::getVal($item,'specification_code');
            $all_sn[] = $c_sn;
            if(!empty($c_code)){
                $all_code[] = $c_code;
            }
        }
        $all_sn = array_unique($all_sn);
        $all_code = array_unique($all_code);

        $merchandise_info_model = new MerchandiseInfoModel();
        $merchandise_list = $merchandise_info_model->getMerchandiseBySnList($all_sn, $store_id);
        $had_sn = [];//该站点已经存在了的sn
        $has_sn_id_dict = [];
        if(!empty($merchandise_list)){
            foreach($merchandise_list as $m){
                $m_sn = $m['merchandise_sn'];
                $had_sn[] = $m_sn;
                $has_sn_id_dict[$m_sn] = $m['id'];
            }
        }
        //需要新增的sn
        $need_add_sn = array_diff($all_sn, $had_sn);
        $merchandise_specification_model = new MerchandiseSpecificationsModel();
        $specification_list = $merchandise_specification_model->getMerchandiseSpecificationsBySnList($all_code, $store_id);
        $had_code = [];//该站点已经存在了的code
        $has_code_id_dict = [];
        if(!empty($specification_list)){
            foreach($specification_list as $s){
                $had_code[] = $s['merchandise_specification_code'];
                $has_code_id_dict[$s['merchandise_specification_code']] =  $s['id'];
            }
        }
        //需要新增的code
        $need_add_code = array_diff($all_code, $had_code);

        $is_sku_error_data = [];
        $has_add_sn = [];
        $has_update_sn = [];
        $cur_time = time();
        $add_merchandise_list = [];
        $update_merchandise_list = [];
        $add_specification_list = [];
        $update_specification_list = [];
        foreach($need_process_data as $item){
            $c_sn = $item['merchandise_sn'];
            $c_code = $item['specification_code'];
            $is_sku = empty($c_code)?ConstantConfig::IS_SKU_FALSE:ConstantConfig::IS_SKU_TRUE;
            $base_info = $base_merchandise_dict[$c_sn];
            $base_is_sku = intval($base_info['is_sku']);
            $base_is_group = intval($base_info['is_group']);
            if($is_sku!=$base_is_sku){
                $is_sku_error_data[] = $c_sn;
                continue;
            }

            if(in_array($c_sn, $need_add_sn)){
                if(!in_array($c_sn,$has_add_sn)){//没有添加过
                    $has_add_sn[] = $c_sn;
                    $store_merchandise_info = [
                        'store_id' => $store_id,
                        'store_platform_type' =>$platform_type,
                        'store_merchandise_id' => ArrayHelper::getValue($item, 'store_merchandise_id', 0),
                        'merchandise_sn' => $c_sn,
                        'merchandise_name' => ArrayHelper::getValue($item, 'merchandise_name', 0),
                        'is_sku' => $is_sku,
                        'is_group' => $base_is_group,
                        'price' => ArrayHelper::getValue($item, 'price', 0),
                        'store_numbers' => 0,
                        'is_sale' => 1, //$c_is_sale,
                        'status' => 0,
                        'created_at' => $cur_time
                    ];
                    $add_merchandise_list[] = $store_merchandise_info;
                }
            }else{
                $has_merchandise_id = $has_sn_id_dict[$c_sn];
                if(!in_array($c_sn, $has_update_sn)){
                    $has_update_sn[] = $c_sn;
                    $store_merchandise_info = [
                        'id'=>$has_merchandise_id,
                        'store_id' => $store_id,
                        'store_platform_type' =>$platform_type,
                        'store_merchandise_id' => ArrayHelper::getValue($item, 'store_merchandise_id', 0),
                        'merchandise_sn' => $c_sn,
                        'merchandise_name' => ArrayHelper::getValue($item, 'merchandise_name', 0),
                        'is_sku' => $is_sku,
                        'is_group' => $base_is_group,
                        'price' => ArrayHelper::getValue($item, 'price', 0),
                        'store_numbers' => 0,
                        'is_sale' => 1, //$c_is_sale,
                        'status' => 0,
                        'created_at' => $cur_time
                    ];
                    $update_merchandise_list[] = $store_merchandise_info;
                }
            }

            if(!empty($c_code) && in_array($c_code, $need_add_code)){
                $store_specification_info = [
//                    'merchandise_id' => 0,
                    'merchandise_sn' => $c_sn,
                    'store_merchandise_id' => ArrayHelper::getValue($item, 'store_merchandise_id', 0),
                    'store_merchandise_specification_id' => ArrayHelper::getValue($item, 'store_specification_id', 0),
                    'merchandise_specification_code' => $c_code,
                    'merchandise_specification_name' => ArrayHelper::getValue($item, 'specification_name', ''),
                    'store_id' => $store_id,
                    'price' => ArrayHelper::getValue($item, 'price', 0),
                    'store_numbers' => 0,
                    'status' => 0,
                    'created_at' => $cur_time
                ];
                $add_specification_list[] = $store_specification_info;
            }else{
                $has_specification_id = $has_code_id_dict[$c_code];
                $store_specification_info = [
                    'id'=>$has_specification_id,
                    'merchandise_sn' => $c_sn,
                    'store_merchandise_id' => ArrayHelper::getValue($item, 'store_merchandise_id', 0),
                    'store_merchandise_specification_id' => ArrayHelper::getValue($item, 'store_specification_id', 0),
                    'merchandise_specification_code' => $c_code,
                    'merchandise_specification_name' => ArrayHelper::getValue($item, 'specification_name', ''),
                    'store_id' => $store_id,
                    'price' => ArrayHelper::getValue($item, 'price', 0),
                    'store_numbers' => 0,
                    'status' => 0,
                    'created_at' => $cur_time
                ];
                $update_specification_list[] = $store_specification_info;
            }
        }

        $sn_id_dict = $merchandise_info_model->createMerchandise($add_merchandise_list);
        if(!empty($update_merchandise_list)){
            $merchandise_info_model->updateMerchandise($update_merchandise_list);
        }

        foreach($add_specification_list as &$add_item){
            $add_sn = $add_item['merchandise_sn'];
            $add_merchandise_id = ArrayHelper::getValue($has_sn_id_dict, $add_sn, 0);
            if($add_merchandise_id==0){
                $add_merchandise_id = ArrayHelper::getValue($sn_id_dict, $add_sn, 0);
            }
            if($add_merchandise_id==0){
                return ['success'=>false, 'msg'=>'程序异常，未能正确获取商品的id'];
            }
            $add_item['merchandise_id'] = $add_merchandise_id;
        }
        $merchandise_specification_model->createMerchandiseSpecifications($add_specification_list);
        $merchandise_specification_model->updateMerchandiseSpecifications($update_specification_list);
        return ['success'=>true , 'is_sku_error_data'=>$is_sku_error_data];
    }

    /**
     * 根据店铺id获取所在的项目类型
     * @param $store_id
     * @return array
     */
    public function getProjectTypeByStoreId($store_id)
    {
        $store_model = new StoresModel();
        $result = $store_model->getProjectTypeByStoreId($store_id);

        $project_type = '';
        foreach ($result as $key=>$value) {
            $project_type = $value['project_type'];
        }

        return $project_type;
    }

}