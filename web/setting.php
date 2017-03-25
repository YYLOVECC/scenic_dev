<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/20
 * Time: 22:12
 */

class setting {
    public static function getGearmanClientConfig()
    {
        $conf = ['host' => '172.17.42.1', 'port' => 4730];
        return $conf;
    }
}