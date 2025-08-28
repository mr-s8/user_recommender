<?php

use yii\db\Migration;

/**
 * Class m250824_065745_user_recommender_recommendation_log
 */
class m250824_065745_user_recommender_recommendation_log extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%user_recommender_recommendation_log}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'recommended_user_id' => $this->integer()->notNull(),
            'score' => $this->float()->notNull(),
            'generation_id' => $this->integer()->notNull(),
            'follows' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_recommender_recommendation_log}}');
    }

}
