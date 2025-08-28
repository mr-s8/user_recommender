<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        // Empfehlungstabelle löschen
        $this->dropTable('user_recommender_recommendation');

        // Vektoren-Tabelle löschen
        $this->dropTable('user_recommender_user_vector');

        $this->dropTable('user_recommendation_generation');

        $this->dropTable('user_recommendation_activity');

        $this->dropTable('user_recommender_recommendation_log');

        $this->dropTable('user_recommendation_fallback');
    }

    public function down()
    {
        echo "uninstall kann nicht rückgängig gemacht werden.\n";
        return false;
    }
}
