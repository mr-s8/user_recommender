<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user_recommender}}`.
 */
class m250630_020944_create_user_recommender_table extends Migration
{
    public function safeUp()
        {
            $this->createTable('user_recommender_recommendation', [
                'user_id' => $this->integer()->notNull(),
                'recommended_user_id' => $this->integer()->notNull(),
                'score' => $this->float()->defaultValue(0),
                'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
                'PRIMARY KEY(user_id, recommended_user_id)',
            ]);

            $this->createIndex('idx_user_id', 'user_recommender_recommendation', 'user_id');

        }

    public function safeDown()
        {
            $this->dropTable('user_recommender_recommendation');
        }
}
