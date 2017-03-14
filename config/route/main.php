<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/2/7
 * Time: 10:19
 */
return [
    //用户管理模块
    'users/ajax/list' => 'users/ajax-users-list',
    //角色管理模块
    'role/ajax/roles' => 'role/ajax-valid-roles',
    'role/ajax/list' => 'role/ajax-role-list',
    'role/ajax/disable' => 'role/ajax-disable-role',
    'role/ajax/enable' => 'role/ajax-enable-role',
    'role/ajax/delete' => 'role/ajax-delete-role',
    'role/ajax/role_feature_privilege' => 'role/ajax-role-feature-privilege',
    'role/ajax/save_feature_privilege' => 'role/ajax-save-role-feature-privilege',
    'role/ajax/role_field_privilege' => 'role/ajax-role-field-privilege',
    'role/ajax/save_field_privilege' => 'role/ajax-save-role-field-privilege',
    'role/ajax/role_data_privilege' => 'role/ajax-role-data-privilege',
    'role/ajax/save_data_privilege' => 'role/ajax-save-role-data-privilege',
    'role/ajax/user_list' => 'role/ajax-user-list',
    'role/<id:\d+>/user_list' => 'role/user-list',
    'role/edit/<id:\d+>' => 'role/edit',
    //功能管理模块
    'features-auth/ajax/list' => 'features-auth/ajax-list',
    'features-auth/ajax/tree' => 'features-auth/ajax-tree',
    'features-auth/ajax/dialog/list' => 'features-auth/ajax-dialog-list',
    'features-auth/ajax/dialog/save' => 'features-auth/ajax-dialog-save',
    'features-auth/edit/<id:\d+>' => 'features-auth/edit',

    //行为管理模块
    'actions/ajax/list' => 'actions/ajax-list',
    'actions/ajax/enable' => 'actions/ajax-enable',

    //order
    'order/ajax/order_list' => 'order/ajax-order-list',
    'order/<id:\d+>' => 'order/detail',
    'order/ajax/to_examine' => 'order/ajax-to-examine',
    'order/ajax/cancel_examine' => 'order/ajax-cancel-examine',
    'order/ajax/export_data' => 'order/ajax-export-data',
    'order/ajax/order_refund_audit' => 'order/ajax-order-refund-audit',

    //scenic
    'scenic-show/ajax/scenic_list' => 'scenic-show/ajax-scenic-list',

    //ticket
    'ticket-show/ajax/ticket_list' => 'ticket-show/ajax-ticket-list',



];