<?php

use yii\db\Migration;

/**
 * Class m250819_000001_user_recommender_activity_logging
 */
class m250819_000001_user_recommender_activity_logging extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Tabelle für Recommendation Generations
        $this->createTable('{{%user_recommendation_generation}}', [
            'id' => $this->primaryKey(),
            'type' => "ENUM('all','latecomer') NOT NULL",
            'run' => $this->integer()->notNull(),
            'started_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'idx-user_recommendation_generation-run',
            '{{%user_recommendation_generation}}',
            'run'
        );

        // Tabelle für Recommendation Activity Logging
        $this->createTable('{{%user_recommendation_activity}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'target_id' => $this->integer()->notNull(),
            'action' => "ENUM('click','reject') NOT NULL",
            'generation_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        // Foreign Key zu user_recommendation_generation
        $this->addForeignKey(
            'fk-user_recommendation_activity-generation_id',
            '{{%user_recommendation_activity}}',
            'generation_id',
            '{{%user_recommendation_generation}}',
            'id',
            'CASCADE'
        );

        // Optional: Index für schnelle Abfragen nach user_id
        $this->createIndex(
            'idx-user_recommendation_activity-user_id',
            '{{%user_recommendation_activity}}',
            'user_id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%user_recommendation_activity}}');
        $this->dropTable('{{%user_recommendation_generation}}');
    }
}
