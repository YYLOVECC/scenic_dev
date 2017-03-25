<?php

namespace app\utils;


use GearmanClient;
use GearmanException;
use HttpException;
use setting;

class GearmanClientParent
{
    private $_gm_client;

    /**
     * 构造函数
     * @param bool $init
     * @throws GearmanException
     * @throws HttpException
     */
    protected function __construct($init = true)
    {
        $this->_gm_client = new GearmanClient();
        if ($init) {
            $gm_conf = setting::getGearmanClientConfig();
            if (empty($gm_conf)) {
                throw new HttpException(500, '缺少Gearman配置参数');
            }

            if (!$this->addServer($gm_conf['host'], $gm_conf['port'])) {
                throw new GearmanException('GearmanException: 添加Server异常');
            }
        }
    }


    /**
     * 添加作业系统服务器
     *
     * @param string $host 作业系统服务器地址
     * @param int $port 作业系统服务器端口
     * @return $this
     * @throws GearmanException
     */
    private function addServer($host, $port)
    {
        if (!$this->_gm_client->addServer($host, $port)) {
            throw new GearmanException('GearmanException: 添加Server异常');
        }
        return $this;
    }

    /**
     * 向作业系统提交作业
     *
     * @param string $functionName 作业名称
     * @param array|mixed $payload 作业数据
     * @param bool $type 作业类型，分为同步作业和异步作业，默认为同步作业
     * @param int $out_time: 超时时间
     * @return array|string
     */
    protected function doWork($functionName, $payload, $type = false, $out_time=5000)
    {
        $payload_str = json_encode($payload);
        if ($type) {
            $this->_gm_client->doBackground($functionName, $payload_str);
            if ($this->_gm_client->returnCode() != GEARMAN_SUCCESS) {
                return ['success' => false, 'msg' => '任务提交失败'];
            } else {
                return ['success' => true];
            }
        } else {
            $this->_gm_client->setTimeout($out_time);
            return json_decode($this->_gm_client->doNormal($functionName, $payload_str), true);
        }
    }
}