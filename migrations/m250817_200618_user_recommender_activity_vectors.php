<?php

use yii\db\Migration;

/**
 * Class m250817_200618_user_recommender_activity_vectors
 */
class m250817_200618_user_recommender_activity_vectors extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_recommender_user_vector', [
            'user_id' => $this->integer()->notNull(),
            'vector' => $this->text()->notNull(), // JSON wird als TEXT gespeichert
            'updated_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'PRIMARY KEY(user_id)',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('user_recommender_user_vector');
    }
}
