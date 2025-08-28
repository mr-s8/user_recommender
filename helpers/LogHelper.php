<?php

namespace humhub\modules\user_recommender\helpers;

use Yii;

class LogHelper
{
    /**
     * Writing text to the log.txt file; only for debug purposes
     */
    public static function write($message)
    {
        $logFile = Yii::getAlias('@humhub/modules/user_recommender/log.txt');
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
    }
}
