<?php

use yii\db\Migration;

/**
 * Class m250819_020630_add_generation_status_and_generation_id
 */
class m250819_020630_add_generation_status_and_generation_id extends Migration
{
    public function safeUp()
    {
        // status-Spalte für die Generationen
        $this->addColumn('user_recommendation_generation', 'status', $this->string()->notNull()->defaultValue('running'));

        // generation_id in recommendations
        $this->addColumn('user_recommender_recommendation', 'generation_id', $this->integer()->null());

        // optional: Foreign Key
        $this->addForeignKey(
            'fk-recommendation-generation',
            'user_recommender_recommendation',
            'generation_id',
            'user_recommendation_generation',
            'id',
            'SET NULL'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-recommendation-generation', 'user_recommender_recommendation');
        $this->dropColumn('user_recommender_recommendation', 'generation_id');
        $this->dropColumn('user_recommendation_generation', 'status');
    }

}
