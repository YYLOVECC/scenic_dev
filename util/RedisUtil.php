<?php
/**
 * redis 操作类
 * User: licong
 * Date: 16/3/16
 * Time: 下午7:25
 */

namespace app\util;

use RedisException;
use Redis;
use Yii;
use yii\db\Exception;

class RedisUtil
{
    private static $_redis = null;
    public $redis;

    public function __construct()
    {
        $redis = new Redis();
        $params = Yii::$app->redis;
        $redis->connect($params->hostname, $params->port);
        $this->redis = $redis;
    }

    private static function _getConnect()
    {
        if (!self::$_redis) {
            $params = Yii::$app->redis;
            $redis = new Redis();
            $redis->connect($params->hostname, $params->port);
            self::$_redis = $redis;
        }
    }

    public static function getRedis()
    {
        self::_getConnect();
        return self::$_redis;
    }

    /**
     * @param null $profession 业务名称
     * @param null $key
     * @param $value
     * @param int $expire 失效时间
     * @return bool
     */
    public static function set($key, $value, $profession = null, $expire = -1)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        if ($expire == null) {
            $expire = Yii::$app->params['redis_default_expire'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            self::$_redis->set($key, $value);
            if ($expire != -1) {
                self::$_redis->setTimeout($key, $expire);
            }
        } catch (RedisException $e) {
            return false;
        }
        return true;
    }

    /**
     * @param null $profession 业务名称
     * @param null $key
     * @return null
     */
    public static function get($key, $profession = null)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            $result = self::$_redis->get($key);
        } catch (RedisException $e) {
            return null;
        }
        return $result;
    }

    /**
     * @param null $key
     * @param null $profession
     * @param $expire
     * @return null
     */
    public static function incr($key, $profession = null, $expire = -1)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        if ($expire == null) {
            $expire = Yii::$app->params['redis_default_expire'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            $result = self::$_redis->incr($key);
            if ($expire != -1) {
                self::$_redis->setTimeout($key, $expire);
            }
        } catch (RedisException $e) {
            return null;
        }
        return $result;
    }

    /**
     * @param null $key
     * @param int $increment
     * @param null $profession
     * @param $expire
     * @return null
     */
    public static function incrBy($key, $increment = 1, $profession = null, $expire = -1)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        if ($expire == null) {
            $expire = Yii::$app->params['redis_default_expire'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            $result = self::$_redis->incrBy($key, $increment);
            if ($expire != -1) {
                self::$_redis->setTimeout($key, $expire);
            }
        } catch (RedisException $e) {
            return null;
        }
        return $result;
    }

    /**
     * @param null $profession 业务名称
     * @param null $key
     * @return bool
     */
    public static function del($key, $profession = null)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            self::$_redis->delete($key);
        } catch (RedisException $e) {
            return false;
        }
        return true;
    }

    /**
     * @param null $profession 业务名称
     * @param null $key
     * @param $field : 域
     * @param $value
     * @param int $expire 失效时间
     * @return bool
     */
    public static function hmset($key, $field, $value, $profession = null, $expire = -1)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        if ($expire == null) {
            $expire = Yii::$app->params['redis_default_expire'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            self::$_redis->hMset($key, [$field=>$value]);
            if ($expire != -1) {
                self::$_redis->setTimeout($key, $expire);
            }
        } catch (RedisException $e) {
            return false;
        }
        return true;
    }

    /**
     * @param null $profession 业务名称
     * @param null $key
     * @param $field : 域
     * @return null
     */
    public static function hmget($key, $field, $profession = null)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            $result = self::$_redis->hMGet($key, [$field]);
        } catch (RedisException $e) {
            return null;
        }
        return $result[$field];
    }

    public static function hGetAll($key, $profession = null)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            $result = self::$_redis->hGetAll($key);
        } catch (RedisException $e) {
            return null;
        }
        return $result;
    }

    public static function hIncrBy($key, $hashKey, $value, $profession = null, $expire = -1)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        if ($expire == null) {
            $expire = Yii::$app->params['redis_default_expire'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            $result = self::$_redis->hIncrBy($key, $hashKey, $value);
            if ($expire != -1) {
                self::$_redis->setTimeout($key, $expire);
            }
            return $result;
        } catch (RedisException $e) {
            return null;
        }
    }

    /**
     * @param null $profession 业务名称
     * @param null $key
     * @param $field
     * @return bool
     */
    public static function hdel($key, $field, $profession = null)
    {
        self::_getConnect();

        if ($profession == null) {
            $profession = Yii::$app->params['redis_default_profession'];
        }

        try {
            $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
            self::$_redis->hDel($key, $field);
        } catch (RedisException $e) {
            return false;
        }
        return true;
    }

    /**
     * 关闭
     */
    public static function close()
    {
        if (self::$_redis) {
            self::$_redis->close();
        }
    }

    /**
     * 返回有序集key中，所有score值介于min和max之间(包括等于min或max)的成员。
     * 有序集成员按score值递减(从小到大)的次序排列。
     * @param $key
     * @param $min: +inf无限小  （0 大于0
     * @param $max: -inf无限大
     * @param $profession
     * @param array $option: ['withscores'=>true, 'limit'=>[offset, count]]
     * @return mixed
     */
    public static function zRangeByScore($key, $min = '+inf', $max = '-inf', $option = [], $profession = null)
    {
        if (empty($key)) {
            return [];
        }
        if (empty($profession)) {
            $profession = Yii::$app->params['redis_default_profession'];
        }
        $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
        self::_getConnect();
        return self::$_redis->zRangeByScore($key, $min, $max, $option);
    }

    /**
     * 将一个或多个member元素加入到集合key当中，已经存在于集合的member元素将被忽略。
     * @param $key
     * @param $score
     * @param $value
     * @param $profession
     * @return mixed
     */
    public static function zAdd($key, $score, $value, $profession = null)
    {
        if (empty($key)) {
            return [];
        }
        if (empty($profession)) {
            $profession = Yii::$app->params['redis_default_profession'];
        }
        $key = Yii::$app->params['redis_name'] . '_' . $profession . '_' . $key;
        self::_getConnect();
        return self::$_redis->zAdd($key, $score, $value);
    }

    public static function multi()
    {
        self::_getConnect();
        self::$_redis->multi();
    }

    public static function exec()
    {
        if (!self::$_redis) {
            throw new Exception('redis connection is null');
        }
        self::$_redis->exec();
    }
}
