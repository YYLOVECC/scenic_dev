<?php
/**
 * Created by PhpStorm.
 * User: teresas
 * Date: 8/11/15
 * Time: 10:38 AM
 */
namespace app\util;

use Yii;
use yii\web\Cookie;

class YiiCookie
{
    /**
     * 获取cookie
     * @param $name:名称
     * @return null
     */
    public static function get($name)
    {
        $cookies = Yii::$app->request->cookies;

        if (isset($cookies[$name])){
//            return StringUtil::decrypt($cookies[$name]->value);
            return $cookies[$name]->value;
        }
        return null;
    }

    /**
     * 设置cookie
     * @param $name :名称
     * @param $value :值
     * @param $expire:过期时间
     */
    public static function set($name, $value, $expire = 0)
    {
        $cookies = Yii::$app->response->cookies;
        $web_cookie = new Cookie(['name'=>$name, 'value'=>$value, 'expire'=>$expire]);
        $cookies->add($web_cookie);
    }

    public static function delete($name)
    {
        $cookies = Yii::$app->response->cookies;

        $cookies->remove($name);
    }
}