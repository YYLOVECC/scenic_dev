<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/2/11
 * Time: 17:09
 */
namespace app\forms;


use yii\base\Model;

class AdminUsersForm extends Model
{
    public $id;
    public $email;
    public $name;
    public $roles_id = 0;
    public $description;

    public function rules()
    {
        return [
            ['id', 'required', 'message' => '员工编号不能为空', 'on' =>'edit'],
            ['name', 'required', 'message' => '员工名称不能为空', 'on' =>['add', 'edit']],
            ['email', 'match','pattern'=>'/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i', 'message' => '邮箱格式不正确', 'on' =>['add', 'edit']],
            ['roles_id', 'required', 'message' => '角色不能为空', 'on' => ['add', 'edit']],
        ];
    }
}