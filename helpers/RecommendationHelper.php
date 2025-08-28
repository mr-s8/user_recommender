<?php
namespace humhub\modules\user_recommender\helpers;
use humhub\modules\user\models\Follow;


class RecommendationHelper
{

    // getting activity vectors per space for a user
    public static function getActivityCountsPerSpace($userId, $spaceIds, $startTime)
    {
        $spaceIdsStr = implode(',', array_map('intval', $spaceIds));

        // Posts
        $posts = (new \yii\db\Query())
            ->select(['cc.pk AS space_id', 'COUNT(*) AS count_posts'])
            ->from('content c')
            ->innerJoin('contentcontainer cc', 'cc.id = c.contentcontainer_id')
            ->where([
                'c.object_model' => 'humhub\\modules\\post\\models\\Post',
                'c.created_by' => $userId,
                'cc.class' => 'humhub\\modules\\space\\models\\Space',
            ])
            ->andWhere(['>=', 'c.created_at', $startTime])
            ->andWhere(['in', 'cc.pk', $spaceIds])
            ->groupBy('cc.pk')
            ->all();

        // Comments
        $comments = (new \yii\db\Query())
            ->select(['cc.pk AS space_id', 'COUNT(*) AS count_comments'])
            ->from('comment com')
            ->innerJoin('activity a', "a.object_model = 'humhub\\modules\\comment\\models\\Comment' AND a.object_id = com.id")
            ->innerJoin('content c', "c.object_model = 'humhub\\modules\\activity\\models\\Activity' AND c.object_id = a.id")
            ->innerJoin('contentcontainer cc', 'cc.id = c.contentcontainer_id')
            ->where([
                'com.created_by' => $userId,
                'cc.class' => 'humhub\\modules\\space\\models\\Space',
            ])
            ->andWhere(['>=', 'com.created_at', $startTime])
            ->andWhere(['in', 'cc.pk', $spaceIds])
            ->groupBy('cc.pk')
            ->all();

        // Likes
        $likes = (new \yii\db\Query())
            ->select(['cc.pk AS space_id', 'COUNT(*) AS count_likes'])
            ->from('`like` lik')
            ->innerJoin('activity a', "a.object_model = 'humhub\\modules\\like\\models\\Like' AND a.object_id = lik.id")
            ->innerJoin('content c', "c.object_model = 'humhub\\modules\\activity\\models\\Activity' AND c.object_id = a.id")
            ->innerJoin('contentcontainer cc', 'cc.id = c.contentcontainer_id')
            ->where([
                'lik.created_by' => $userId,
                'cc.class' => 'humhub\\modules\\space\\models\\Space',
            ])
            ->andWhere(['>=', 'lik.created_at', $startTime])
            ->andWhere(['in', 'cc.pk', $spaceIds])
            ->groupBy('cc.pk')
            ->all();

        // initialising vector
        $result = [];
        foreach ($spaceIds as $spaceId) {
            $result[$spaceId] = 0;
        }

        // posts are weighted with 3
        foreach ($posts as $p) {
            $result[$p['space_id']] += $p['count_posts'] * 3;
        }

        // comments are weighted with 2
        foreach ($comments as $c) {
            $result[$c['space_id']] += $c['count_comments'] * 2;
        }

        // likes are weighted with 1
        foreach ($likes as $l) {
            $result[$l['space_id']] += $l['count_likes'] * 1;
        }

        return $result;
    }

    public static function cosineSimilarity(array $vecA, array $vecB)
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($vecA as $key => $valA) {
            $valB = $vecB[$key] ?? 0;
            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA == 0 || $normB == 0) {
            return 0; 
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    public static function normalizeVector(array $vec)
    {
        $norm = 0;
        foreach ($vec as $val) {
            $norm += $val * $val;
        }
        $norm = sqrt($norm);
        if ($norm == 0) return $vec;

        foreach ($vec as $key => $val) {
            $vec[$key] = $val / $norm;
        }

        return $vec;
    }

    // saves an actibiy vector to the db
    public static function saveUserVector($userId, array $vector)
    {
    $json = json_encode($vector);

    \Yii::$app->db->createCommand()->upsert('user_recommender_user_vector', [
        'user_id' => $userId,
        'vector' => $json,
        'updated_at' => new \yii\db\Expression('NOW()'),
    ])->execute();
    }

    
    // save recommendations to db
    public static function saveRecommendations($userId, $recommendations, $generationId)
    {
        // del current rec
        \Yii::$app->db->createCommand()
            ->delete('user_recommender_recommendation', ['user_id' => $userId])
            ->execute();

        // save new rec
        foreach ($recommendations as $recommendedUserId => $score) {
            \Yii::$app->db->createCommand()->insert('user_recommender_recommendation', [
                'user_id' => $userId,
                'recommended_user_id' => $recommendedUserId,
                'score' => $score,
                'generation_id' => $generationId,
                'created_at' => new \yii\db\Expression('NOW()'),
            ])->execute();
        }
    }

    // getting common likes for two users in all spaces and on profile posts
    protected static function getCommonLikesScores(
        int $userIdA,
        int $userIdB,
        array $spaceIds,
        string $lookbackTime
    ): array {
        // Space-Likes
        $spaceLikes = (new \yii\db\Query())
            ->select(['cc.pk AS space_id', 'COUNT(DISTINCT lik.object_model, lik.object_id) AS score'])
            ->from('`like` lik')
            ->innerJoin('activity a', "a.object_model = 'humhub\\modules\\like\\models\\Like' AND a.object_id = lik.id")
            ->innerJoin('content c', "c.object_model = 'humhub\\modules\\activity\\models\\Activity' AND c.object_id = a.id")
            ->innerJoin('contentcontainer cc', 'cc.id = c.contentcontainer_id')
            ->where(['lik.created_by' => [$userIdA, $userIdB]])
            ->andWhere(['cc.class' => 'humhub\\modules\\space\\models\\Space'])
            ->andWhere(['>=', 'lik.created_at', $lookbackTime])
            ->andWhere(['in', 'cc.pk', $spaceIds])
            ->andWhere(new \yii\db\Expression("
                EXISTS (
                    SELECT 1
                    FROM `like` lik2
                    WHERE lik2.object_model = lik.object_model
                    AND lik2.object_id = lik.object_id
                    AND lik2.created_by IN (:userA, :userB)
                    GROUP BY lik2.object_model, lik2.object_id
                    HAVING COUNT(DISTINCT lik2.created_by) = 2
                )
            ", [':userA' => $userIdA, ':userB' => $userIdB]))
            ->groupBy('cc.pk')
            ->all();

        $commonLikesVector = [];
        foreach ($spaceLikes as $like) {
            $commonLikesVector[$like['space_id']] = (float)$like['score'];
        }

        // Profil-Post-Likes
        $profileLikes = (new \yii\db\Query())
            ->select(['cc.pk AS profile_user_id', 'COUNT(DISTINCT lik.object_model, lik.object_id) AS score'])
            ->from('`like` lik')
            ->innerJoin('activity a', "a.object_model = 'humhub\\modules\\like\\models\\Like' AND a.object_id = lik.id")
            ->innerJoin('content c', "c.object_model = 'humhub\\modules\\activity\\models\\Activity' AND c.object_id = a.id")
            ->innerJoin('contentcontainer cc', 'cc.id = c.contentcontainer_id')
            ->where(['lik.created_by' => [$userIdA, $userIdB]])
            ->andWhere(['cc.class' => 'humhub\\modules\\user\\models\\User'])
            ->andWhere(['>=', 'lik.created_at', $lookbackTime])
            ->andWhere(new \yii\db\Expression("
                EXISTS (
                    SELECT 1
                    FROM `like` lik2
                    WHERE lik2.object_model = lik.object_model
                    AND lik2.object_id = lik.object_id
                    AND lik2.created_by IN (:userA, :userB)
                    GROUP BY lik2.object_model, lik2.object_id
                    HAVING COUNT(DISTINCT lik2.created_by) = 2
                )
            ", [':userA' => $userIdA, ':userB' => $userIdB]))
            ->groupBy('cc.pk')
            ->all();

        $profilePostScore = 0.0;
        foreach ($profileLikes as $like) {
            $profilePostScore += (float)$like['score'];
        }

        return [$commonLikesVector, $profilePostScore];
    }


    // combining activity vectors with the common likes scores
    public static function mergeVectorsWithCommonLikes(
    array $vecA,
    array $vecB,
    int $userIdA,
    int $userIdB,
    array $spaces,
    $lookbackTime
    ): array {
        // get common likes
        [$commonLikesVector, $profilePostScore] = self::getCommonLikesScores($userIdA, $userIdB, $spaces, $lookbackTime);

        // copy vectors
        $augmentedA = $vecA;
        $augmentedB = $vecB;

        // add likes score
        foreach ($commonLikesVector as $spaceId => $score) {
            if (!isset($augmentedA[$spaceId])) $augmentedA[$spaceId] = 0.0;
            if (!isset($augmentedB[$spaceId])) $augmentedB[$spaceId] = 0.0;
            $augmentedA[$spaceId] += $score;
            $augmentedB[$spaceId] += $score;
        }

        // add profile post dimension
        if (!isset($augmentedA['profile_posts'])) $augmentedA['profile_posts'] = 0.0;
        if (!isset($augmentedB['profile_posts'])) $augmentedB['profile_posts'] = 0.0;
        $augmentedA['profile_posts'] += $profilePostScore;
        $augmentedB['profile_posts'] += $profilePostScore;

        return [$augmentedA, $augmentedB];
    }


    public static function isFollowing(int $userId, int $otherUserId): bool
    {
        return Follow::find()
            ->where([
                'user_id'   => $userId,
                'object_id' => $otherUserId,
                'object_model' => 'humhub\modules\user\models\User'
            ])
            ->exists();
    }

    public static function getFollowedUserIds(int $userId): array
    {
        $rows = \Yii::$app->db->createCommand("
            SELECT object_id
            FROM user_follow
            WHERE user_id = :userId
            AND object_model = :model
        ", [
            ':userId' => $userId,
            ':model'  => 'humhub\modules\user\models\User'
        ])->queryColumn();

        // save as associative array
        $assoc = [];
        foreach ($rows as $id) {
            $assoc[(int)$id] = true;
        }

        return $assoc;
    }


    // appends recommendations to a log table
    public static function logRecommendations($userId, $recommendations, $generationId, $followedUserIds)
    {
        $db = \Yii::$app->db;

        foreach ($recommendations as $recommendedUserId => $score) {
            $isFollowing = isset($followedUserIds[$recommendedUserId]) ? 1 : 0;

            $db->createCommand()->insert('user_recommender_recommendation_log', [
                'user_id'             => $userId,
                'recommended_user_id' => $recommendedUserId,
                'score'               => $score,
                'generation_id'       => $generationId,
                'follows'        => $isFollowing,
                'created_at'          => new \yii\db\Expression('NOW()'),
            ])->execute();
        }

    }


    public static function cleanLogs(int $maxRunsToKeep = 10): void
    {
        $db = \Yii::$app->db;

        // getting generation ids from runs
        $runs = $db->createCommand("
            SELECT urg.id, urg.run
            FROM user_recommender_recommendation_log url
            JOIN user_recommendation_generation urg ON urg.id = url.generation_id
            GROUP BY urg.id, urg.run
            ORDER BY urg.run DESC
        ")->queryAll();

        if (count($runs) <= $maxRunsToKeep) {
            return; 
        }

        // get the runs which are old enough to be deleted
        $runsToDelete = array_slice($runs, $maxRunsToKeep);

        if (!empty($runsToDelete)) {
            $generationIds = array_column($runsToDelete, 'id');

            // delete logs
            $db->createCommand()->delete('user_recommender_recommendation_log', [
                'generation_id' => $generationIds
            ])->execute();
        }
    }


    public static function cleanFallbacks(int $maxRunsToKeep = 10): void
{
    $db = \Yii::$app->db;



    $runs = $db->createCommand("
        SELECT urg.id, urg.run
        FROM user_recommendation_fallback urf
        JOIN user_recommendation_generation urg ON urg.id = urf.generation_id
        GROUP BY urg.id, urg.run
        ORDER BY urg.run DESC
    ")->queryAll();




    if (count($runs) <= $maxRunsToKeep) {
        return; 
    }


    $runsToDelete = array_slice($runs, $maxRunsToKeep);



    if (!empty($runsToDelete)) {
        $generationIds = array_column($runsToDelete, 'id');


        $db->createCommand()->delete('user_recommendation_fallback', [
            'generation_id' => $generationIds
        ])->execute();
    }
}


public static function cleanGenerations(int $maxRunsToKeep = 10): void
{
    $db = \Yii::$app->db;



    $runs = $db->createCommand("
        SELECT id, run
        FROM user_recommendation_generation
        ORDER BY run DESC
    ")->queryAll();


    if (count($runs) <= $maxRunsToKeep) {
        return;
    }

    $runsToDelete = array_slice($runs, $maxRunsToKeep);
    $generationIds = array_column($runsToDelete, 'id');


    if (!empty($generationIds)) {
        $deleted = $db->createCommand()->delete('user_recommendation_generation', [
            'id' => $generationIds
        ])->execute();

    }
}

}
