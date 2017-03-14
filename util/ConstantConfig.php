<?php
/**
 * User: licong
 * Date: 2/10/17
 * Time: 4:31 PM
 */
namespace app\util;


class ConstantConfig
{
    const ADMIN_COOKIE_NAME = 'auth_token';
    const ENABLE_TRUE = 1;  //启用
    const ENABLE_FALSE = 0; //禁用
    const ENABLE_ALL = '-1'; //停启用状态不限

    const STATUS_DEFAULT = 0; //正常
    const STATUS_DELETE = 1; //删除
    const STATUS_ALL = -1; //状态不限

    const STATE_ALL = -1;
    const STATE_TRUE = 1;
    const STATE_FALSE = 0;

    // 商品上下架状态
    const ON_SHELF_TRUE = 1; //未上架
    const ON_SHELF_FALSE = 0; //已上架

    //排序类型
    const ORDINAL_ASC = 'ASC'; //升序
    const ORDINAL_DESC = 'DESC'; //降序

    //时间周期
    const UNIT_TIME_MINUTE = 1; //分钟
    const UNIT_TIME_HOUR = 2; //小时
    const UNIT_TIME_DAY = 3; //天
    const UNIT_TIME_WEEK = 4; //周
    //支付方式
    const PAY_TYPE_ONLINE = 1; //线上支付
    const CREDIT_PAYMENT = 2;// 授信支付

    //支付途径
    const PAY_MODE_DEFAULT = 0;
    const PAY_MODE_ALIPAY = 1;  //支付宝
    const PAY_MODE_WECHAT = 2;  // 微信
    const PAY_MODE_UNION = 3;  // 银联

    //支付状态
    const PAY_STATUS_UNPAID = 0;//默认未付款
    const PAY_STATUS_PAID = 1 ;//已付款
    const PAY_STATUS_REFUNDING = 2;//退款中
    const PAY_STATUS_REFUNDED = 3;//退款完成
    const PAY_STATUS_CANCEL_REFUND = 4;//退款取消

    //订单状态
    const ORDER_STATUS_DEFAULT = 1;//下单成功
    const ORDER_STATUS_WAITING_FOR_CONFIRMATION = 2;//待审核
    const ORDER_STATUS_COMPLETE = 3;//交易完成 已入园
    const ORDER_STATUS_CANCEL = 4;//交易取消

    //客审，反审
    const CONFIRMATION_ACTION_TYPE_TO_EXAMINE = 'to_examine';
    const CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE = 'cancel_examine';

    //门票类型
    const TICKET_TYPE_ADULT = 0;//成人
    const TICKET_TYPE_CHILD = 1; //儿童
    //资源类别
    const RESOURCE_NOT = 0;
    const RESOURCE_ORDER = 1; //订单



    /**
     * 订单状态数组
     * @return array
     */
    public static function orderStatusArray()
    {
        return [
            self::ORDER_STATUS_DEFAULT=>'下单成功',
            self::ORDER_STATUS_WAITING_FOR_CONFIRMATION=>'待确认',
            self::ORDER_STATUS_COMPLETE => '交易完成',
            self::ORDER_STATUS_CANCEL => '交易取消'
        ];
    }
    public static function payStatusArray()
    {
        return [
            self::PAY_STATUS_UNPAID => '未付款',
            self::PAY_STATUS_PAID=> '已付款',
            self::PAY_STATUS_REFUNDING => '退款中',
            self::PAY_STATUS_REFUNDED => '退款完成',
            self::PAY_STATUS_CANCEL_REFUND =>'退款取消'
        ];
    }

    /**Mode
     * 支付类型
     * @return array
     */
    public static function payTypeArr()
    {
        return [
            self::PAY_TYPE_ONLINE => "在线支付",
            self::CREDIT_PAYMENT => "授信支付",
        ];
    }

    /**
     * 支付途径
     * @return array
     */
    public static function payModeArr()
    {
        return [
            self::PAY_MODE_ALIPAY => "支付宝",
            self::PAY_MODE_WECHAT => "微信",
            self::PAY_MODE_UNION=> "银联",
        ];
    }

    /**
     * 门票类型
     * @return array
     */
    public static  function ticketTypeArr()
    {
        return [
            self::TICKET_TYPE_ADULT => '成人',
            self::TICKET_TYPE_CHILD  =>'儿童'
        ];
    }

    public static function confirmationActionType()
    {
        return [ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE,
            ConstantConfig::CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE];
    }
    /**
     * 审核行为关联的订单状态
     * @return array
     */
    public static function confirmationRelationOrderStatus()
    {
        return [ConstantConfig::CONFIRMATION_ACTION_TYPE_TO_EXAMINE => ConstantConfig::ORDER_STATUS_COMPLETE,
            ConstantConfig::CONFIRMATION_ACTION_TYPE_CANCEL_EXAMINE => ConstantConfig::ORDER_STATUS_WAITING_FOR_CONFIRMATION
        ];
    }
    public static function allResourceType()
    {
        return [ConstantConfig::RESOURCE_NOT, ConstantConfig::RESOURCE_ORDER];
    }

}