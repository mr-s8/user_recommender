<?php

namespace humhub\modules\user_recommender\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use humhub\modules\user\models\User;
use yii\filters\AccessControl;
use humhub\modules\user_recommender\models\SettingsForm;
use yii\db\Query;
use humhub\modules\user_recommender\services\RecommendationService;
use \humhub\modules\user_recommender\helpers\RecommendationHelper;

class RecommenderController extends Controller
{
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

    // getting the recommendations for a user
    public function actionRecommendations() {

       Yii::$app->response->format = Response::FORMAT_JSON;

        $userId = (int)Yii::$app->user->id;

        // get value from the settings
        $settingsForm = new SettingsForm();
        $settingsForm->loadSettings();
        $generateLimit = (int)$settingsForm->recommendationGenerateCount;  // number of users to generate
        $displayLimit = (int)$settingsForm->recommendationDisplayCount; // numbers of users to show on the first page
        $cooldownRuns = (int)$settingsForm->rejectCooldownRuns;
        $threshold     = (int)$settingsForm->fallbackTitleThreshold;

        //sleep(0); // for testing loading animations


        // Get the ids from the people to recommend to the current user
        $recommendedUsers = (new Query())
        ->select(['recommended_user_id', 'generation_id'])
        ->from('user_recommender_recommendation')
        ->where(['user_id' => $userId])
        ->orderBy(['score' => SORT_DESC])
        ->limit($generateLimit)
        ->all();

        // be sure those are integers
        $recommendedUsers = array_map(function($row) {
            return [
                'recommended_user_id' => (int)$row['recommended_user_id'],
                'generation_id' => (int)$row['generation_id'],
            ];
        }, $recommendedUsers);


        $recommendationIds = array_column($recommendedUsers, 'recommended_user_id');

        // check if there are not enough recommendations
        if (count($recommendedUsers) < $generateLimit) {
            $needed = $generateLimit - count($recommendedUsers);

            $maxRun = (new Query())
                ->select('MAX(run)')
                ->from('user_recommendation_generation')
                ->scalar();

            // get the userse that have been previously rejected
            $rejectedUserIds = (new Query())
                ->select('ura.target_id')
                ->from('user_recommendation_activity ura')
                ->innerJoin('user_recommendation_generation urg', 'ura.generation_id = urg.id')
                ->where([
                    'ura.user_id' => $userId,
                    'ura.action'  => 'reject',
                ])
                ->andWhere(['>=', 'urg.run', max(0, $maxRun - $cooldownRuns)])
                ->column();

            $rejectedUserIds = array_map('intval', $rejectedUserIds);

                
            $latestGenerationId = (new Query())
                ->select('id')
                ->from('user_recommendation_generation')
                ->orderBy(['run' => SORT_DESC])
                ->limit(1)
                ->scalar();

            // get number of fallback user
            $fallbacks = (new Query())
                ->select(['fallback_user_id', 'generation_id', 'activity_score'])
                ->from('user_recommendation_fallback')
                ->andWhere(['generation_id' => $latestGenerationId])
                ->andWhere(['not in', 'fallback_user_id', $recommendationIds])
                ->andWhere(['not in', 'fallback_user_id', $rejectedUserIds]) // NEU: keine rejecteten im Cooldown
                ->andWhere(['not in', 'fallback_user_id', $userId]) // NEU: keine rejecteten im Cooldown
                ->orderBy(['activity_score' => SORT_DESC])
                ->limit($needed)
                ->all();
            
            // typecasts
            $fallbacks = array_map(function($row) {
                return [
                    'fallback_user_id' => (int)$row['fallback_user_id'],
                    'generation_id'    => (int)$row['generation_id'],
                    'activity_score'   => (float)$row['activity_score'], // optional float
                ];
            }, $fallbacks);



            // combining the users
            foreach ($fallbacks as $fb) {
                $recommendedUsers[] = [
                    'recommended_user_id' => (int)$fb['fallback_user_id'],
                    'generation_id'       => (int)$fb['generation_id'],
                    'score'               => (float)$fb['activity_score'],
                    'is_fallback'         => true,  
                ];
            }
        }


        // if set in the settings, shuffle the recommendations; fallback users dont supress actual recommended users from the visible recommendations
        if ($settingsForm->shuffleRecommendations) {
            $nonFallbacks = array_values(array_filter($recommendedUsers, fn($r) => empty($r['is_fallback'])));
            $fallbacks    = array_values(array_filter($recommendedUsers, fn($r) => !empty($r['is_fallback'])));

            shuffle($nonFallbacks);
            shuffle($fallbacks);

            $displayLimit = (int)$settingsForm->recommendationDisplayCount;

            //  number of non fallbacks
            $nNonFallbacks = count($nonFallbacks);

            // fallbacks on first page
            $slotsLeft = max(0, $displayLimit - $nNonFallbacks);

            // divide the fallbacks
            $fallbacksForFirstPage = array_slice($fallbacks, 0, $slotsLeft);
            $fallbacksRemaining    = array_slice($fallbacks, $slotsLeft);

            // first page = real recommendations + chosen fallbacks, then shuffle
            $firstPage = array_merge($nonFallbacks, $fallbacksForFirstPage);
            shuffle($firstPage);

            // append the rest
            $recommendedUsers = array_merge($firstPage, $fallbacksRemaining);
        } else {
            // if not shuffle, show real recommendations first, then fallbacks; each sorted by score
            $recommendedUsers = array_merge(
                array_filter($recommendedUsers, fn($r) => empty($r['is_fallback'])),
                array_filter($recommendedUsers, fn($r) => !empty($r['is_fallback']))
            );
        }

        // if set in the settings, get the users ids that are mutual recommendations and that exceed the mutual threshold
        $highlightIds = [];
        if ($settingsForm->highlightMutual) {
            $mutualThreshold = (float)$settingsForm->highlightMutualThresh;

            foreach ($recommendedUsers as $rec) {
                if (!empty($rec['is_fallback'])) {
                    continue; // dont take fallbacks into account
                }

                $otherUserId = $rec['recommended_user_id'];

                // check the recommended user if he has the current user as recommendation; also check the score
                $exists = (new Query())
                    ->from('user_recommender_recommendation')
                    ->where([
                        'user_id'            => $otherUserId,
                        'recommended_user_id'=> $userId,
                    ])
                    ->andWhere(['>=', 'score', $mutualThreshold])
                    ->exists();

                if ($exists) {
                    $highlightIds[] = (int)$otherUserId;
                }
            }
        }



        $userIds = array_column($recommendedUsers, 'recommended_user_id');
        $userIds = array_map('intval', $userIds);

        $users = User::find()
        ->where(['id' => $userIds])
        ->indexBy('id')  
        ->all();


        // collecting necessary information for the frontend
        $usersForFrontend = array_map(function($rec) use ($users) {
            $userId = (int)$rec['recommended_user_id'];
            if (!isset($users[$userId])) {
                return null; // skippen
            }
            return [
                'id' => $userId,
                'name' => $users[$userId]->displayName,
                'url' => $users[$userId]->getUrl(),
                'image' => $users[$userId]->getProfileImage()->getUrl(),
                'generation_id' => (int)$rec['generation_id'],
            ];
        }, $recommendedUsers);

        $usersForFrontend = array_filter($usersForFrontend); 


        // getting the right title for the widget depending on the number of fallback recommendations
        $fallbackCount = count(array_filter($recommendedUsers, fn($r) => !empty($r['is_fallback'])));
        $title = ($fallbackCount >= $threshold)
        ? Yii::t('UserRecommenderModule.base', 'Interessante Nutzer')
        : Yii::t('UserRecommenderModule.base', 'Nutzer mit ähnlichen Interessen');



        return [
            'users' => $usersForFrontend,
            'toDisplayCount' => $displayLimit,
            'title' => $title,
            'highlight' => $highlightIds,
        ];

    }

    // triggered on rejection click; deleted a recommendation of a user if it wasnt a fallback; saves the decision to db
    public function actionRemove()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // information from the user
        $recommendedUserId = Yii::$app->request->post('userId');
        $generationId = Yii::$app->request->post('generationId');
        $isMutual      =  Yii::$app->request->post('highlighted', 0); // 0 oder 1

        // dont allow inputs other than numbers
        if (!is_numeric($recommendedUserId) || !is_numeric($generationId)) {
            return ['success' => false, 'error' => 'Ungültige Eingabe'];
        }

        if (!is_numeric($isMutual) || !in_array((int)$isMutual, [0, 1], true)) {
            return ['success' => false, 'error' => 'Ungültige Eingabe'];
        }

        $isMutual = (int)$isMutual;
        $currentUserId     = (int)Yii::$app->user->id;
        $recommendedUserId = (int)$recommendedUserId;
        $generationId      = (int)$generationId;

        try {
        // checking if it was a recommendation
        $isRecommendation = (new \yii\db\Query())
            ->from('user_recommender_recommendation')
            ->where([
                'user_id' => $currentUserId,
                'recommended_user_id' => $recommendedUserId,
                'generation_id' => $generationId,
            ])
            ->exists();
        
        // if it wasnt a recommendation it has to be a fallback; if not manipulated...
        $type = $isRecommendation ? 'recommendation' : 'fallback';

        
        // if it was an actual recommendation, it can be deleted
        if ($type == 'recommendation') {
            $deleted = Yii::$app->db->createCommand()
                ->delete('user_recommender_recommendation', [
                    'user_id' => $currentUserId,
                    'recommended_user_id' => $recommendedUserId,
                ])
                ->execute();
        } 

        // checking if the user is following the target
        $follows = RecommendationHelper::isFollowing($currentUserId, $recommendedUserId) ? 1 : 0;

        // saving the decision of the user
        Yii::$app->db->createCommand()
            ->insert('user_recommendation_activity', [
                'user_id' => $currentUserId,
                'target_id' => $recommendedUserId,
                'action' => 'reject',
                'generation_id' => $generationId,
                'type' => $type,
                'following'     => $follows,
                'mutual'        => $isMutual, 
                'created_at' => date('Y-m-d H:i:s'),
            ])
            ->execute();

        } catch (\yii\db\Exception $e) {
            Yii::error("Error in actionRemove: " . $e->getMessage(), __METHOD__);
            return ['success' => false, 'error' => 'Error while handeling request'];
        }

        return ['success' => true];
    }


    // logging clicks on the recommendations
    public function actionLogClick()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // information from the user
        $targetId = Yii::$app->request->post('userId');
        $generationId = Yii::$app->request->post('generationId');
        $isMutual      =  Yii::$app->request->post('highlighted', 0); // 0 oder 1

        // dont allow inputs other than numbers
        if (!is_numeric($targetId) || !is_numeric($generationId)) {
            return ['success' => false, 'error' => 'Ungültige Eingabe'];
        }

        if (!is_numeric($isMutual) || !in_array((int)$isMutual, [0, 1], true)) {
            return ['success' => false, 'error' => 'Ungültige Eingabe'];
        }

        $isMutual = (int)$isMutual;
        $currentUserId     = (int)Yii::$app->user->id;
        $targetId = (int)$targetId;
        $generationId      = (int)$generationId;
        

        // checking if it was a recommendation
        try {
        $isRecommendation = (new \yii\db\Query())
            ->from('user_recommender_recommendation')
            ->where([
                'user_id' => $currentUserId,
                'recommended_user_id' => $targetId,
                'generation_id' => $generationId,
            ])
            ->exists();
        
        // if it wasnt a recommendation it has to be a fallback; if not manipulated...
        $type = $isRecommendation ? 'recommendation' : 'fallback';
        
        // checking if the user is following the target
        $follows = RecommendationHelper::isFollowing($currentUserId, $targetId) ? 1 : 0;
        
        // logging the user action
        Yii::$app->db->createCommand()
            ->insert('user_recommendation_activity', [
                'user_id' => $currentUserId,
                'target_id' => $targetId,
                'action' => 'click',
                'generation_id' => $generationId,
                'type' => $type,
                'following'     => $follows,
                'mutual'        => $isMutual,  
                'created_at' => date('Y-m-d H:i:s'),
            ])
            ->execute();

        } catch (\yii\db\Exception $e) {
            Yii::error("Error in actionLogClick: " . $e->getMessage(), __METHOD__);
            return ['success' => false, 'error' => 'Error while handeling request.'];
        }

        return ['success' => true];
    }


}


