<?php
namespace app\util;

use yii\base\Exception;

class Tools
{
    /**
     * 通过curl 提交数据
     * @param $url
     * @param $post_data
     * @param $headers
     * @return mixed|null
     */
    public static function requestPost($url, $post_data, $headers=null)
    {

        $ch = curl_init();
        try {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            if(!empty($hearers) || is_array($headers)){
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }else{
                curl_setopt($ch, CURLOPT_HEADER, 0);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $res = curl_exec($ch);
        } catch (Exception $e) {
            print $e->getMessage();
            $res = null;
        }
        curl_close($ch);

        return $res;
    }

    /**
     * 验证签名
     * @param $secret_key
     * @param $params
     * @return bool
     */
    public static function checkSign($secret_key, $params)
    {
        $get_sign = $params['sign'];

        unset($params['sign']);
        $sign = self::createSign($secret_key, $params);

        if ($get_sign == $sign) {
            return true;
        }

        return false;
    }

    /**
     * 生成签名标签
     * @param $secret_key
     * @param $params
     * @return string
     */
    public static function createSign($secret_key, $params)
    {
        ksort($params);

        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . $value;
        }
        $string = $secret_key . $string . $secret_key;
        $sign = md5($string);
        return $sign;
    }


}