<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/22
 * Time: 21:50
 */
namespace app\models;
use Yii;
class UsersModel{

    public function getUserInfo() {
        $sql = "SELECT id, name from users WHERE role>0 and status = 0";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }
}