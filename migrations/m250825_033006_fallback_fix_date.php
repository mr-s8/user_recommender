<?php

use yii\db\Migration;

/**
 * Class m250825_033006_fallback_fix_date
 */
class m250825_033006_fallback_fix_date extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Spalte created_at von int → datetime ändern
        $this->alterColumn('user_recommendation_fallback', 'created_at', $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Zurück auf int (falls du wirklich revert brauchst)
        $this->alterColumn('user_recommendation_fallback', 'created_at', $this->integer()->notNull());
    }
}
