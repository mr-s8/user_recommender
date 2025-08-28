<?php

use yii\db\Migration;

/**
 * Class m250821_012755_user_recommender_add_indices
 */
class m250821_012755_user_recommender_add_indices extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Kombinierter Index auf user_id + action + generation_id für schnelle Rejection-Abfragen
        $this->createIndex(
            'idx-user_recommendation_activity-user_action_generation',
            '{{%user_recommendation_activity}}',
            ['user_id', 'action', 'generation_id']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-user_recommendation_activity-user_action_generation', '{{%user_recommendation_activity}}');
    }
}
