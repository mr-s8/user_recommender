<?php

namespace humhub\modules\user_recommender\widgets;

use humhub\modules\user\models\User;
use humhub\components\Widget;
use Yii;
use yii\db\Query;
use humhub\modules\user_recommender\models\SettingsForm;
use humhub\modules\user_recommender\services\RecommendationService;
use \humhub\modules\user_recommender\helpers\LogHelper;
use yii\helpers\Url;

class FiveSquaresWidget extends Widget
{
    public function run()
    {
        $userId = Yii::$app->user->id;

        // gettings settings values
        $settingsForm = new SettingsForm();
        $settingsForm->loadSettings();
        $showSurveyInfo    = (bool)$settingsForm->showSurveyInfo;
        $surveyTitle       = $settingsForm->surveyTitle;
        $surveyText        = $settingsForm->surveyText;
        $surveyButtonText  = $settingsForm->surveyButtonText;
        $surveyButtonLink  = $settingsForm->surveyButtonLink;

        // dynamically generated urls (sometimes prettyurl is used sometimes not)
        $logClickUrl = Url::to(['/user_recommender/recommender/log-click']);
        $removeUrl   = Url::to(['/user_recommender/recommender/remove']);


       

        return $this->render('@user_recommender/views/widgets/fiveSquaresWidget', [
            'showSurveyInfo'    => $showSurveyInfo,
            'surveyTitle'       => $surveyTitle,
            'surveyText'        => $surveyText,
            'surveyButtonText'  => $surveyButtonText,
            'surveyButtonLink'  => $surveyButtonLink,
            'logClickUrl'       => $logClickUrl,
            'removeUrl'         => $removeUrl,
            'recommendationsUrl'=> Url::to(['/user_recommender/recommender/recommendations']),
        ]);

        }
}


