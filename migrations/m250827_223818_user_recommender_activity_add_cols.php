<?php

use yii\db\Migration;

/**
 * Class m250827_223818_user_recommender_activity_add_cols
 */
class m250827_223818_user_recommender_activity_add_cols extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user_recommendation_activity}}', 'following', $this->boolean()->notNull()->defaultValue(0)->after('generation_id'));
        $this->addColumn('{{%user_recommendation_activity}}', 'mutual', $this->boolean()->notNull()->defaultValue(0)->after('following'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%user_recommendation_activity}}', 'mutual');
        $this->dropColumn('{{%user_recommendation_activity}}', 'following');
    }
}
