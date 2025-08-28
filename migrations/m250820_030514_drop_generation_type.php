<?php

use yii\db\Migration;

/**
 * Class m250820_030514_drop_generation_type
 */
class m250820_030514_drop_generation_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $table = 'user_recommendation_generation';
        $schema = $this->db->schema->getTableSchema($table, true);

        if (isset($schema->columns['type'])) {
            $this->dropColumn($table, 'type');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Falls du die Migration zurückrollen willst, kannst du die Spalte wieder hinzufügen
        $this->addColumn('user_recommendation_generation', 'type', $this->string()->defaultValue(null));
    }
}
