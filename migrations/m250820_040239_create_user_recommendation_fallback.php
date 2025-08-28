<?php

use yii\db\Migration;

/**
 * Class m250820_040239_create_user_recommendation_fallback
 */
class m250820_040239_create_user_recommendation_fallback extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_recommendation_fallback', [
            'id' => $this->primaryKey(),
            'fallback_user_id' => $this->integer()->notNull(),
            'activity_score' => $this->decimal(10,4)->notNull()->defaultValue(0),
            'generation_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('user_recommendation_fallback');
    }
}
