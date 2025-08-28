<?php

namespace humhub\modules\user_recommender\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\user_recommender\jobs\GenerateRecommendationsJob;
use yii\filters\AccessControl;
use humhub\modules\user_recommender\Events;


use humhub\commands\CronController;


use Yii;

class TestController extends Controller {

    // only registered users should access these routes
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], 
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return 'Test OK';
    }

    /*
    public function actionRunJob()
    {   
        $settings = Yii::$app->getModule('user_recommender')->settings;
        $settings->delete('lastRecommendationRun');
        //Events::onHourlyCron(null);
        $cron = new CronController('cron', \Yii::$app);
        $cron -> trigger(CronController::EVENT_ON_HOURLY_RUN);
        
    }*/
        
}