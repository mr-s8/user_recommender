<?php

namespace humhub\modules\user_recommender;

use humhub\modules\user_recommender\widgets\FiveSquaresWidget;
use humhub\modules\user_recommender\jobs\GenerateRecommendationsJob;
use Yii;

class Events
{
    public static function onAboutSidebarInit($event)
    {
        $event->sender->addWidget(FiveSquaresWidget::class, [], ['sortOrder' => 200]);
    }

    public static function onHourlyCron($event)
    {
        Yii::$app->queue->push(new GenerateRecommendationsJob());
    }
}
