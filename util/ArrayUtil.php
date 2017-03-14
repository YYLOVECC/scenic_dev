<?php
/**
 * Created by PhpStorm.
 * User: jaimie
 * Date: 8/7/15
 * Time: 5:26 PM
 */

namespace app\util;

class ArrayUtil {
    public static function getVal($arr, $key, $def_val=''){
        if (empty($arr) || empty($key)){
            return $def_val;
        }
        if (array_key_exists($key, $arr) && !empty($arr[$key])){
            return $arr[$key];
        }
        return $def_val;
    }

    /**
     * 该函数类似于PHP5.5中的`array_columns`功能，详情见http://php.net/manual/zh/function.array-column.php
     * @param $data
     * @param $key
     * @return array
     */
    public static function dictToList($data, $key)
    {
        if (empty($data) || empty($key)) {
            return [];
        }

        return array_map(function($n) use ($key) {
            if (array_key_exists($key, $n)) {
                return $n[$key];
            }
        }, $data);
    }

    /**
     * @param $data
     * @param $key
     * @param null $value
     * @return array
     */
    public static function listToDict($data, $key, $value=null)
    {
        if (empty($data) || empty($key)) {
            return [];
        }

        $res = [];
        array_map(function($n) use (&$res, $key, $value) {
            if (array_key_exists($key, $n)) {
                if(!empty($value) && array_key_exists($value, $n)){
                    $value_res = $n[$value];
                }else{
                    $value_res = $n;
                }
                $res[$n[$key]] = $value_res;

            }
        }, $data);
        return $res;
    }
}