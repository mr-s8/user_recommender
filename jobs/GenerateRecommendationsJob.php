<?php

namespace humhub\modules\user_recommender\jobs;

use humhub\modules\user_recommender\services\RecommendationService;
use \humhub\modules\user_recommender\helpers\LogHelper;
use humhub\modules\queue\ActiveJob;

class GenerateRecommendationsJob extends ActiveJob
{
    public $description = "Generiert Nutzer-Empfehlungen automatisch.";


    public function run()
    {
        $settings = \Yii::$app->getModule('user_recommender')->settings;

        // get time and day from settings
        $regenerationTime = $settings->get('regenerationTime', '03:00'); 
        $now = new \DateTime('now');
        $currentTimestamp = $now->getTimestamp();
        

        // 1) check if there has been an execution in the last x hours; if yes there can be no exec right now; x has to be bigger than the tolerance
        // if x is high, a changed exec time might need a day to take affect
        $lastRun = $settings->get('lastRecommendationRun', null);
        if ($lastRun !== null && ($currentTimestamp - (int)$lastRun) < 3 * 3600) {

            return;
        }

        // 2) RegenerationTime (hh:mm)
        [$hour, $minute] = explode(':', $regenerationTime);

        // 3) Get week days from settings
        $regenerationDays = json_decode($settings->get('regenerationDays', '["mon","wed","fri"]'), true);
        $weekMap = ['mon'=>1, 'tue'=>2, 'wed'=>3, 'thu'=>4, 'fri'=>5, 'sat'=>6, 'sun'=>7];
        $scheduledDays = array_map(fn($day) => $weekMap[$day], $regenerationDays);

        // 4) check if there should have been an execution with a tolerance of two hours;
        $windowOk = false;
        foreach ([0, -1] as $dayOffset) {
            $candidateDate = (clone $now)->modify("$dayOffset day");
            $candidateWeekday = (int)$candidateDate->format('N');

            if (!in_array($candidateWeekday, $scheduledDays)) {
                continue; // falscher Tag, überspringen
            }

            $scheduledTime = (clone $candidateDate)->setTime((int)$hour, (int)$minute)->getTimestamp();
            $diffMinutes = ($currentTimestamp - $scheduledTime) / 60;


            if ($diffMinutes >= 0 && $diffMinutes <= 120) {
                $windowOk = true;
                break;
            }
        }

        // no scheduled exec in last two hours
        if (!$windowOk) {
            return;
        }

        // 5) Exec Job
        $settings->set('lastRecommendationRun', $currentTimestamp);
        try {
            RecommendationService::generateRecommendationsForAllUsers();
        } catch (\Throwable $e) {
            
        }
        


    }

}

