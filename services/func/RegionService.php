<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 8/4/15
 * Time: 8:47 PM
 */

namespace app\services\func;


use app\services\region\CAreaService;
use app\services\region\CCityService;
use app\services\region\CProvinceService;

class RegionService {
    public function getRegions($target, $parent){
        if (empty($target) || (!in_array($target, ['selProvinces','selProvinces2']) and empty($parent))){
            return ['success'=>false, 'smg'=>'参数传递错误'];
        }

        if (in_array($target, ['selProvinces','selProvinces2']) ){
            $cprovince_service = new CProvinceService();
            $result = $cprovince_service->getAllProvince();
            $r_result = ['success'=>true, 'data'=>$result, 'count'=>count($result)];
        }elseif(in_array($target, ['selCities','selCities2'])){
            $ccity_service = new CCityService();
            $r_result = $ccity_service->getCitiesByProvinceId($parent);
        }elseif(in_array($target, ['selAreas','selAreas2'])){
            $carea_service = new CAreaService();
            $r_result = $carea_service->getAreasByCityId($parent);
        }else{
            $r_result = ['success'=>false, 'msg'=>'查询类型错误'];
        }

        $r_result['target'] = $target;
        return $r_result;
    }
} 