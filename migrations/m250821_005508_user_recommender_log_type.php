<?php

use yii\db\Migration;

/**
 * Class m250821_005508_user_recommender_log_type
 */
class m250821_005508_user_recommender_log_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(
            'user_recommendation_activity',
            'type',
            "ENUM('recommendation', 'fallback') NOT NULL DEFAULT 'recommendation' COMMENT 'Type of the logged user: recommendation or fallback'"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('user_recommendation_activity', 'type');
    }
}
