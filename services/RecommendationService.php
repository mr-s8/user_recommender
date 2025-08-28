<?php
namespace humhub\modules\user_recommender\services;

use humhub\modules\user\models\User;
use humhub\modules\space\models\Space;
use humhub\modules\user_recommender\models\SettingsForm;
use humhub\modules\user_recommender\helpers\RecommendationHelper;
use \humhub\modules\user_recommender\helpers\LogHelper;

class RecommendationService
{   
    // Function that generate the recommendations for every user; it is called by the cron job
    public static function generateRecommendationsForAllUsers()
    {
        
         // Get Settings Data
        $settings = new SettingsForm();
        $settings->loadSettings();
        $limit = (int)$settings->recommendationGenerateCount;
        $similarityThreshold = isset($settings->similarityThreshold)
            ? (float)$settings->similarityThreshold
            : 0.0;

        $cooldownRuns  = $settings->rejectCooldownRuns;
        $lookbackDays = (int)$settings->lookbackDays;

        $fallbackMultiplier       = (float) $settings->fallbackMultiplier;       
        $recommendationMultiplier = (float) $settings->recommendationMultiplier;
        $unfollowedPriorityCount  = (int) $settings->unfollowedPriorityCount;
        $unfollowedSimilarityThreshold  = (float) $settings->unfollowedSimilarityThreshold;
       



        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            // Create a new run, with status "running"
            $newRun = (int)$db->createCommand('SELECT COALESCE(MAX(run),0) + 1 FROM user_recommendation_generation')->queryScalar();
            $db->createCommand()->insert('user_recommendation_generation', [
                'run' => $newRun,
                'started_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'status' => 'running',
            ])->execute();

            $generationId = (int)$db->getLastInsertID();

            // New Run Number (increases on every calculation by 1)
            $maxRun = $newRun;


            // Calculate the point in time up to which activity data is considered.
            $lookbackTime = (new \DateTime())->modify("-{$lookbackDays} days")->format('Y-m-d H:i:s');

            // 1. Get all spaces
           $spaces = Space::find()->select(['id'])->asArray()->all();
            $spaceIds = [];
            if (!empty($spaces)) {
                // array_column liefert evtl. strings -> intval sauberstellen
                $spaceIds = array_map('intval', array_column($spaces, 'id'));
            }

            // 2. Get all users
            $users = User::find()->select(['id'])->asArray()->all();
            $userIds = [];
            if (!empty($users)) {
                $userIds = array_map('intval', array_column($users, 'id'));
            }

            // 3. Build activity vectors (UserID => [spaceId => Value, ...])
            $vectors = [];
            foreach ($userIds as $userId) {
                $uid = (int)$userId;
                // force int for the key
                $vectors[$uid] = RecommendationHelper::getActivityCountsPerSpace($uid, $spaceIds, $lookbackTime);
            }

            // 3b) Generate fallbacks (most active users) from the activity vectors
            self::generateFallbackRecommendations($vectors, (int)$limit, $generationId, $fallbackMultiplier);


            // 4. Calculate Recommendations for each user pair 
            // creating a list to cache scores
            $pairScores = [];

            foreach ($userIds as $userId) {
                $userId = (int)$userId;

                // --- Filter inactive users ---
                $sumActivity = array_sum($vectors[$userId]);
                if ($sumActivity <= 0) {
                    continue; 
                }


                // get the users which were rejected by the current user and which are still in cooldown
                $rejectedUserIds = \Yii::$app->db->createCommand("
                    SELECT ura.target_id
                    FROM user_recommendation_activity AS ura
                    JOIN user_recommendation_generation AS urg ON ura.generation_id = urg.id
                    WHERE ura.user_id = :userId
                    AND ura.action = 'reject'
                    AND urg.run >= :minRun
                ", [
                    ':userId' => $userId,
                    ':minRun' => max(0, $maxRun - $cooldownRuns)
                ])->queryColumn();
                
                // forcing int type
                $rejectedUserIds = !empty($rejectedUserIds) ? array_map('intval', $rejectedUserIds) : [];

                // get the users the current users follows (as associative array)
                $followedUserIds = RecommendationHelper::getFollowedUserIds($userId);   // this is guaranteed to return ints

                // get the top k recommendations, considering the similarity threshold, the rejected users, a multiplier for k to get some variation, and the number of unfollowed users that should at least show up
                $recommendations = self::calculateKnnRecommendations($userId, $vectors, (int)$limit, $similarityThreshold, $rejectedUserIds, $followedUserIds, $recommendationMultiplier, $spaces, $lookbackTime, $unfollowedPriorityCount, $unfollowedSimilarityThreshold, $pairScores);

                // saving the recommendations for the current user to the database
                RecommendationHelper::saveRecommendations($userId, $recommendations, $generationId);
                
                // putting the recommendations into the log table 
                RecommendationHelper::logRecommendations($userId, $recommendations, $generationId, $followedUserIds);
                
                // saving the activity vector from the current user (without the commmon likes ofc, since they are unique for every user pair)
                RecommendationHelper::saveUserVector($userId, $vectors[$userId]);
            }

            // Set the status of the current run to finished
            $db->createCommand()->update('user_recommendation_generation', ['status' => 'finished'], ['id' => $generationId])->execute();

            // clean the tables that would grow indefinetly; uncomment or change parameter to change the number of runs to keep
            RecommendationHelper::cleanLogs(15);

            RecommendationHelper::cleanFallbacks(15);

            RecommendationHelper::cleanGenerations(15);

            $transaction->commit();

        } catch (\Exception $e) {
            $transaction->rollBack();
            if (isset($generationId)) {
                $db->createCommand()->update('user_recommendation_generation', ['status' => 'failed'], ['id' => $generationId])->execute();
            }
            throw $e;
        }
    }





    // calculating the top k recommendations for a user and his activity vector
    protected static function calculateKnnRecommendations($userId, $vectors, $limit, float $similarityThreshold, $rejectedUserIds, $followedUserIds, $recommendationMultiplier, $spaces, $lookbackTime, $unfollowedPriorityCount, $unfollowedSimilarityThreshold, &$pairScores)
    {   
        // ensure int userId
        $userId = (int)$userId;

        // getting the user activity vector
        $userVector = $vectors[$userId] ?? [];

        
        $allScores = [];
        $unfollowedScores = [];

        // comparing the user activty vector to every other user and his activity vector
        foreach ($vectors as $otherUserId => $otherVector) {

            $otherUserId = (int)$otherUserId;

            // dont compare the user to itself
            if ($userId === $otherUserId) continue;
            


            // dont compare the user to a currently rejected one
            if (in_array($otherUserId, $rejectedUserIds, true)) continue;

            // generating the pair key to check if the score has already been calculated
            $pairKey = ($userId < $otherUserId)
            ? "{$userId}-{$otherUserId}"
            : "{$otherUserId}-{$userId}";
            
            // if the score for the user pair has already been calculated use it, if not calculate it
            if (isset($pairScores[$pairKey])) {
                $score = $pairScores[$pairKey];

            } else {

            // get the new activity vectors which now include common likes per space and also the common likes on profile posts
            [$augA, $augB] = RecommendationHelper::mergeVectorsWithCommonLikes(
                $userVector,
                $otherVector,
                $userId,
                $otherUserId,
                $spaces,
                $lookbackTime
            );

            // normalizing vectors to length 1
            $augA = RecommendationHelper::normalizeVector($augA);
            $augB = RecommendationHelper::normalizeVector($augB);

            // calculating cosine similarity
            $score = RecommendationHelper::cosineSimilarity($augA, $augB);

            
            


            // cache the score
            $pairScores[$pairKey] = $score;
            
            }

            // storing the users that are above the similarity threshold in a list
            if ($score >= $similarityThreshold) {
                $allScores[$otherUserId] = $score;
            }




            // storing users that are currently not followed by the current users and that beat the similarity threshold for not followed users in a list
            if ($score >= $unfollowedSimilarityThreshold && !isset($followedUserIds[$otherUserId])) {
                $unfollowedScores[$otherUserId] = $score;
            }

        
    

            
        }



        // Sorting the list by score
        arsort($allScores, SORT_NUMERIC);
        arsort($unfollowedScores, SORT_NUMERIC);




        // Applying the multiplier to k; we will later draw k users at random from the slightly longer result list to get some variation/randomness
        $poolSize = (int)round($limit * $recommendationMultiplier);
        // account for edge cases, where the new poolsize is smaller/bigger than the dataset (?)
        $poolSize = max(1, min($poolSize, count($allScores)));

        // Get the top k*multiplier users
        $topPool = array_slice($allScores, 0, $poolSize, true);

        // Shuffle these users to get the wanted variance
        $userIds = array_keys($topPool);
        shuffle($userIds);

        // draw the first k userse from the shuffled list; this will be the preliminary result
        $final = [];
        foreach (array_slice($userIds, 0, $limit) as $uid) {
            $final[(int)$uid] = $allScores[(int)$uid];
        }

        // if the admin has set a minimum requirement for the number of users not yet followed by the current user
        if ($unfollowedPriorityCount > 0) {

            // Check how many unfollowed users are alrealdy in the preliminary result
            $alreadyUnfollowed = array_intersect_key($unfollowedScores, $final);
            $alreadyCount = count($alreadyUnfollowed);

            // If there need to be more not followed users in the recommendations
            if ($alreadyCount < $unfollowedPriorityCount) {

                // get the number of unfollowed users needed to meet the minimum requirement
                $needed = $unfollowedPriorityCount - $alreadyCount;

                // get the unfollowed users from before that are not already in the preliminary result
                $candidates = array_diff_key($unfollowedScores, $final);

                // If we have such candidates
                if (!empty($candidates)) {

                    // needed unfollowed users (still sorted)
                    $bestUnfollowed = array_slice($candidates, 0, $needed, true);


                    // if final is not full, we can just add these users, no need to replace with other recommendations
                    if (count($final) < $limit) {
                        foreach ($bestUnfollowed as $uid => $score) {
                            if (count($final) >= $limit) break;
                            $final[(int)$uid] = $score;
                        }
                    } else {

                        // Replace the users with the worst scores in the preliminary result with the needed unfollowed users (best score first)
                        asort($final, SORT_NUMERIC);

                        // only the ones that are not already unfollowed users should be able to be replaced
                        $removable = array_diff(array_keys($final), array_keys($unfollowedScores));


                        // these are going to be replaced with the unfollowed recommendations
                        $toRemove = array_slice($removable, 0, count($bestUnfollowed), true);

                        // Replace
                        foreach ($toRemove as $uid) {
                            unset($final[$uid]);
                        }
                        foreach ($bestUnfollowed as $uid => $score) {
                            $final[(int)$uid] = $score;
                        }
                    }
                }
            }

        }

        arsort($final, SORT_NUMERIC);

        return $final;
    }

    // generate the global fallbacks by summing up the activity vectors and selecting the top ones
    protected static function generateFallbackRecommendations(array $vectors, int $limit, int $generationId, float $fallbackMultiplier = 2.0): void
    {
        $db = \Yii::$app->db;
        
        if ($limit <= 0 || empty($vectors)) {
            return;
        }

        $activityScores = []; 
        foreach ($vectors as $userId => $vector) {
            $userId = (int)$userId;
            $sum = 0.0;

            foreach ($vector as $val) {
                $sum += (float)$val;
            }

            if ($sum > 0) {
                $activityScores[$userId] = $sum;
            }
        }

        if (empty($activityScores)) {
            return;
        }

        arsort($activityScores, SORT_NUMERIC);

        // getting the poolsize, by taking the multiplier into account
        $poolSizeTotal = count($activityScores);
        $poolSize = (int)round($limit * $fallbackMultiplier);

        // edge cases
        if ($poolSize < 1) {
            $poolSize = 1;
        }
        if ($poolSize > $poolSizeTotal) {
            $poolSize = $poolSizeTotal;
        }

        // select top pool
        $topPoolAssoc = array_slice($activityScores, 0, $poolSize, true);
        $topUserIds   = array_keys($topPoolAssoc);

        // Shuffle for variance
        shuffle($topUserIds);

        // final number of fallbacks to save
        $finalCount = min($limit, count($topUserIds));
        if ($finalCount <= 0) {
            return;
        }

        // getting the top k user ids
        $selectedUserIds = array_slice($topUserIds, 0, $finalCount);

        // preparing for batch insert
        $rows = [];
        $nowExpr = new \yii\db\Expression('NOW()');
        foreach ($selectedUserIds as $uid) {
            $rows[] = [
                'generation_id'  => $generationId,
                'fallback_user_id'        => (int)$uid,
                'activity_score' => (float)$activityScores[$uid],
                'created_at'     => $nowExpr,
            ];
        }

        // insert into db
        if (!empty($rows)) {
            $db->createCommand()->batchInsert(
                'user_recommendation_fallback',
                ['generation_id', 'fallback_user_id', 'activity_score', 'created_at'],
                $rows
            )->execute();
        }
    }

    

}

