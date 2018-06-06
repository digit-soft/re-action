<?php

namespace Reaction\Rbac\Migrations;

use Reaction;
use Reaction\Db\ConnectionInterface;
use Reaction\Db\Migration;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Rbac\DbManager;
use function Reaction\Promise\allInOrder;

/**
 * Initializes RBAC tables.
 */
class m140506_102106_rbac_init extends Migration
{
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
     * Check that DB is MSSQL
     * @return bool
     */
    protected function isMSSQL()
    {
        $driver = $this->db->getDriverName();
        return $driver === 'mssql' || $driver === 'sqlsrv' || $driver === 'dblib';
    }

    /**
     * Check that DB is Oracle
     * @return bool
     */
    protected function isOracle()
    {
        return $this->db->getDriverName() === 'oci';
    }

    /**
     * {@inheritdoc}
     */
    public function safeUp(ConnectionInterface $connection)
    {
        $authManager = $this->getAuthManager();
        $this->db = $authManager->db;

        $tableOptions = null;
        if ($this->db->getDriverName() === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $promises = [];

        $promises[] = $this->createTable($authManager->ruleTable, [
            'name' => $this->string(64)->notNull(),
            'data' => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'PRIMARY KEY ([[name]])',
        ], $tableOptions);

        $promises[] = $this->createTable($authManager->itemTable, [
            'name' => $this->string(64)->notNull(),
            'type' => $this->smallInteger()->notNull(),
            'description' => $this->text(),
            'rule_name' => $this->string(64),
            'data' => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'PRIMARY KEY ([[name]])',
            'FOREIGN KEY ([[rule_name]]) REFERENCES ' . $authManager->ruleTable . ' ([[name]])' .
                $this->buildFkClause('ON DELETE SET NULL', 'ON UPDATE CASCADE'),
        ], $tableOptions);
        $promises[] = $this->createIndex('idx-auth_item-type', $authManager->itemTable, 'type');

        $promises[] = $this->createTable($authManager->itemChildTable, [
            'parent' => $this->string(64)->notNull(),
            'child' => $this->string(64)->notNull(),
            'PRIMARY KEY ([[parent]], [[child]])',
            'FOREIGN KEY ([[parent]]) REFERENCES ' . $authManager->itemTable . ' ([[name]])' .
                $this->buildFkClause('ON DELETE CASCADE', 'ON UPDATE CASCADE'),
            'FOREIGN KEY ([[child]]) REFERENCES ' . $authManager->itemTable . ' ([[name]])' .
                $this->buildFkClause('ON DELETE CASCADE', 'ON UPDATE CASCADE'),
        ], $tableOptions);

        $promises[] = $this->createTable($authManager->assignmentTable, [
            'item_name' => $this->string(64)->notNull(),
            'user_id' => $this->string(64)->notNull(),
            'created_at' => $this->integer(),
            'PRIMARY KEY ([[item_name]], [[user_id]])',
            'FOREIGN KEY ([[item_name]]) REFERENCES ' . $authManager->itemTable . ' ([[name]])' .
                $this->buildFkClause('ON DELETE CASCADE', 'ON UPDATE CASCADE'),
        ], $tableOptions);

        if ($this->isMSSQL()) {
            $promises[] = $this->execute("CREATE TRIGGER dbo.trigger_auth_item_child
            ON dbo.{$authManager->itemTable}
            INSTEAD OF DELETE, UPDATE
            AS
            DECLARE @old_name VARCHAR (64) = (SELECT name FROM deleted)
            DECLARE @new_name VARCHAR (64) = (SELECT name FROM inserted)
            BEGIN
            IF COLUMNS_UPDATED() > 0
                BEGIN
                    IF @old_name <> @new_name
                    BEGIN
                        ALTER TABLE {$authManager->itemChildTable} NOCHECK CONSTRAINT FK__auth_item__child;
                        UPDATE {$authManager->itemChildTable} SET child = @new_name WHERE child = @old_name;
                    END
                UPDATE {$authManager->itemTable}
                SET name = (SELECT name FROM inserted),
                type = (SELECT type FROM inserted),
                description = (SELECT description FROM inserted),
                rule_name = (SELECT rule_name FROM inserted),
                data = (SELECT data FROM inserted),
                created_at = (SELECT created_at FROM inserted),
                updated_at = (SELECT updated_at FROM inserted)
                WHERE name IN (SELECT name FROM deleted)
                IF @old_name <> @new_name
                    BEGIN
                        ALTER TABLE {$authManager->itemChildTable} CHECK CONSTRAINT FK__auth_item__child;
                    END
                END
                ELSE
                    BEGIN
                        DELETE FROM dbo.{$authManager->itemChildTable} WHERE parent IN (SELECT name FROM deleted) OR child IN (SELECT name FROM deleted);
                        DELETE FROM dbo.{$authManager->itemTable} WHERE name IN (SELECT name FROM deleted);
                    END
            END;");
        }
        return allInOrder($promises);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(ConnectionInterface $connection)
    {
        $authManager = $this->getAuthManager();
        $this->db = $authManager->db;

        $promises = [];
        if ($this->isMSSQL()) {
            $promises[] = $this->execute('DROP TRIGGER dbo.trigger_auth_item_child;');
        }

        $promises[] = $this->dropTable($authManager->assignmentTable);
        $promises[] = $this->dropTable($authManager->itemChildTable);
        $promises[] = $this->dropTable($authManager->itemTable);
        $promises[] = $this->dropTable($authManager->ruleTable);
        return allInOrder($promises);
    }

    /**
     * Build foreign key clause string
     * @param string $delete
     * @param string $update
     * @return string
     */
    protected function buildFkClause($delete = '', $update = '')
    {
        if ($this->isMSSQL()) {
            return '';
        }

        if ($this->isOracle()) {
            return ' ' . $delete;
        }

        return implode(' ', ['', $delete, $update]);
    }
}
