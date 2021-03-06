<?php

namespace Reaction\Web\Sessions\Migrations;

use Reaction\Db\ConnectionInterface;
use Reaction\Db\Migration;
use function Reaction\Promise\allInOrder;

class m060618_095625_add_table_for_session_archive extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(ConnectionInterface $connection)
    {
        $promises = [];
        $promises[] = $this->createTable('{{%session_archive}}', [
            'sid' => $this->string(48)->notNull()->comment('Session ID'),
            'data' => $this->text()->notNull()->comment('Session data'),
            'updated_at' => $this->integer()->unsigned()->notNull()->comment('Update timestamp')
        ]);
        $promises[] = $this->addPrimaryKey('session_archive_pk', '{{%session_archive}}', ['sid']);
        return allInOrder($promises);
    }

    /**
     * @inheritdoc
     */
    public function safeDown(ConnectionInterface $connection)
    {
        return $this->dropTable('{{%session_archive}}');
    }
}