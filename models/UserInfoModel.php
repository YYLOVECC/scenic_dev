<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 2017/3/24
 * Time: 13:25
 */
namespace app\models;
use Yii;
use PDO;
use yii\base\Exception;

class UserInfoModel
{
    /**
     * 更新用户所拥金额
     * @param $user_id
     * @param $paid_price
     * @throws Exception
     */
    public function updatePrice($user_id, $paid_price){
        $sql = "UPDATE user_info SET money = money+:paid_price WHERE user_id = :user_id";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        try {
            $command->bindParam(':paid_price', $paid_price, PDO::PARAM_INT);
            $command->bindParam(':user_id', $user_id,PDO::PARAM_INT);
            $command->execute();
        } catch (Exception $e) {
            throw $e;
        }


    }

}