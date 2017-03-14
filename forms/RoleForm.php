<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/2/11
 * Time: 17:10
 */
namespace app\forms;
use app\models\RolesModel;
use Yii;
use yii\base\Model;

class RoleForm extends Model
{
    public $role_id;
    public $role_name;
    public $description;
    public $level;
    public $is_enable;
    public $parent_id;

    public function rules()
    {
        return [
            [['role_name', ], 'required', 'message'=>'角色名称不能为空', 'on'=>['add', 'edit']],
            [['level', 'parent_id'], 'required', 'on'=>['add', 'edit']],
            [['role_id'], 'required', 'on'=>'edit'],
            ['role_name', 'checkRoleName', 'on'=>'edit'],
            ['role_name', 'roleUnique', 'on'=>'add'],
        ];
    }

    /**
     * 同级角色名唯一性验证
     */
    public function roleUnique()
    {
        $connection = Yii::$app->db;
        $connection->open();
        $role_model = new RolesModel();
        $role_model->setName($this->role_name);
        $role_model->setLevel($this->level);
        $role_model->setParentId($this->parent_id);
        if($role_model->checkRoleExist())
            $this->addError('role_name', '角色已经存在');
        $connection->close();
    }

    /**
     * 修改时检测角色名
     */
    public function checkRoleName()
    {
        $connection = Yii::$app->db;
        $connection->open();
        $role_model = new RolesModel();
        $role_model->setName($this->role_name);
        $role_model->setLevel($this->level);
        $role_model->setParentId($this->parent_id);
        $result = $role_model->checkRoleExist();
        $connection->close();
        if(count($result) > 1 || ($result && $result[0]['id']!=$this->role_id)){
            $this->addError('role_name', '角色已经存在');
        }
    }
}