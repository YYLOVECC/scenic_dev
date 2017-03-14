<?php
namespace app\forms;


use yii\base\Model;

class ModulesForm extends Model
{
    public $id = 0;
    public $name;
    public $parent_id;
    public $page_url;
    public $description;
    public $is_display = 1;

}