<?php

use humhub\modules\dashboard\widgets\Sidebar;
use humhub\commands\CronController;
use humhub\modules\user_recommender\Events;

return [
    'id' => 'user_recommender',
    'class' => 'humhub\modules\user_recommender\Module',
    'namespace' => 'humhub\modules\user_recommender',
    'events' => [
        [
            'class' => Sidebar::class, 
            'event' => Sidebar::EVENT_INIT,
            'callback' => [Events::class, 'onAboutSidebarInit'],
        ],
        [
            'class' => CronController::class, 
            'event' => CronController::EVENT_ON_HOURLY_RUN, 
            'callback' => [Events::class, 'onHourlyCron']
        ],
    ],
];
