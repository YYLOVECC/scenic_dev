<?php
namespace app\forms;


use yii\base\Model;

class ActionsForm extends Model
{
    public $id;
    public $name;
    public $e_name;
    public $description;

    public function rules()
    {
        return [
            ['name', 'required', 'message' => '行为名不能为空'],
            ['e_name', 'required', 'message' => '行为英文名不能为空'],
        ];
    }
}