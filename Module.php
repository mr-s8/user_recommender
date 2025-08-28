<?php

namespace humhub\modules\user_recommender;

use humhub\components\Module as BaseModule;
use yii\base\Event;
use yii\helpers\Url;
use humhub\modules\dashboard\widgets\Sidebar;

class Module extends BaseModule
{
    public function getName()
    {
        return 'User Recommender';
    }

    public function getDescription()
    {
        return 'Zeigt ein Widget mit vorgeschlagenen Usern.';
    }

    public function enable()
    {
        parent::enable();
        
        Event::on(
            Sidebar::class,
            'init',  
            [\humhub\modules\user_recommender\Events::class, 'onAboutSidebarInit']
        );

    }

    public function getConfigUrl()
    {
        return Url::to(['/user_recommender/admin/index']);
    }


}
