<?php
/**
 * Created by PhpStorm.
 * User: teresa
 * Date: 9/20/16
 * Time: 11:47 AM
 */

namespace app\util;

class StringUtil
{
    public static function formatTelephone($telephone)
    {
        if(empty($telephone)) {
            return $telephone;
        }
        $tel_len = strlen($telephone);
        if($tel_len <= 8) {
            return $telephone;
        }
        return sprintf('%s%s%s', substr($telephone, 0, $tel_len-8), '****', substr($telephone, $tel_len-4));
    }
}