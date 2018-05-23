<?php

namespace Reaction\Db;

use Reaction\Events\EventEmitterWildcardInterface;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromiseInterface;

/**
 * Interface ConnectionInterface
 * @package Reaction\Db
 * @property DatabaseInterface $db
 */
interface ConnectionInterface extends EventEmitterWildcardInterface
{
    const EVENT_CLOSE = 'close';

    /**
     * Execute SQL statement string
     * @param string $sql Statement SQL string
     * @param array  $params Statement parameters
     * @param bool   $lazy Use lazy promise
     * @return ExtendedPromiseInterface
     */
    public function executeSql($sql, $params = [], $lazy = true);

    /**
     * Create DB command
     * @param string|null $sql
     * @param array       $params
     * @return CommandInterface
     */
    public function createCommand($sql = null, $params = []);

    /**
     * Begin transaction
     * @param string|null $isolationLevel
     * @return ExtendedPromiseInterface
     */
    public function beginTransaction($isolationLevel = null);

    /**
     * Commit transaction
     * @return LazyPromiseInterface
     */
    public function commitTransaction();

    /**
     * Rolls back whole transaction
     * @return LazyPromiseInterface
     */
    public function rollBackTransaction();

    /**
     * Creates a new savepoint.
     * @param string $name the savepoint name
     * @return LazyPromiseInterface
     */
    public function createSavepoint($name);

    /**
     * Releases an existing savepoint.
     * @param string $name the savepoint name
     * @return LazyPromiseInterface
     */
    public function releaseSavepoint($name);

    /**
     * Rolls back to a previously created savepoint.
     * @param string $name the savepoint name
     * @return LazyPromiseInterface
     */
    public function rollBackSavepoint($name);

    /**
     * Sets the isolation level of the current transaction.
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]], [[Transaction::REPEATABLE_READ]]
     * and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific syntax to be used
     * after `SET TRANSACTION ISOLATION LEVEL`.
     * @return LazyPromiseInterface
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    public function setTransactionIsolationLevel($level);

    /**
     * Close connection
     */
    public function close();
}