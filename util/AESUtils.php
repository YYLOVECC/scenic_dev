<?php
namespace app\util;

use Yii;

class AESUtils {

    const ALGORITHM = "rijndael-128";
    const MODE = "cbc";

    /**
     * AES加密
     * @param $data
     * @param string $key
     * @param string $iv
     * @return string
     */
    public static function encyrpt($data, $key='', $iv='') {
        // Add an input string according to PKCS#7
        $block = mcrypt_get_block_size(AESUtils::ALGORITHM, AESUtils::MODE);
        $pad = $block - (strlen($data) % $block);
        $data .= str_repeat(chr($pad), $pad);

        $key = $key?$key:Yii::$app->params['aes_key'];
        $iv = $iv?$iv:Yii::$app->params['aes_iv'];

        // aes encrypt
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv);
        return bin2hex($encrypted);
    }

    /**
     * AES解密
     * @param $data
     * @param $key
     * @param $iv
     * @return string
     */
    public static function decrypt($data, $key='', $iv='') {
        // aes decrypt
        $decoded = pack("H*" , $data);

        $key = $key?$key:Yii::$app->params['aes_key'];
        $iv = $iv?$iv:Yii::$app->params['aes_iv'];

        $plain = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $decoded, MCRYPT_MODE_CBC, $iv);

        // remove the PKCS#7 padding from a text string
        $block = mcrypt_get_block_size (AESUtils::ALGORITHM, AESUtils::MODE);
        $pad = ord($plain[($len = strlen($plain)) - 1]);
        return substr($plain, 0, strlen($plain) - $pad);
    }

}