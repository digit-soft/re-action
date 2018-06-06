<?php

namespace Reaction\Rbac\Migrations;

use Reaction;
use Reaction\Db\ConnectionInterface;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Rbac\DbManager;
use Reaction\Db\Migration;

/**
 * Adds index on `user_id` column in `auth_assignment` table for performance reasons.
 *
 * @see https://github.com/yiisoft/yii2/pull/14765
 */
class m170907_052038_rbac_add_index_on_auth_assignment_user_id extends Migration
{
    public $column = 'user_id';
    public $index = 'auth_assignment_user_id_idx';

    /**
     * @throws \Reaction\Exceptions\InvalidConfigException
     * @return DbManager
     */
    protected function getAuthManager()
    {
        $authManager = Reaction::$app->getAuthManager();
        if (!$authManager instanceof DbManager) {
            throw new InvalidConfigException('You should configure "authManager" component to use database before executing this migration.');
        }

        return $authManager;
    }

    /**
     * {@inheritdoc}
     */
    public function safeUp(ConnectionInterface $connection)
    {
        $authManager = $this->getAuthManager();
        return $this->createIndex($this->index, $authManager->assignmentTable, $this->column);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(ConnectionInterface $connection)
    {
        $authManager = $this->getAuthManager();
        return $this->dropIndex($this->index, $authManager->assignmentTable);
    }
}
