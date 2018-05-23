<?php

namespace Reaction\Db;

use Reaction\Base\Component;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromiseInterface;
use function Reaction\Promise\allInOrder;
use function Reaction\Promise\resolve;

/**
 * Class Connection.
 * Used for isolated transactions
 * @package Reaction\Db
 */
class Connection extends Component implements ConnectionInterface
{
    /** @var DatabaseInterface */
    public $db;

    /**
     * Begin transaction
     * @param string|null $isolationLevel
     * @return ExtendedPromiseInterface
     */
    public function beginTransaction($isolationLevel = null)
    {
        $beginCmd = $this->createCommand("BEGIN")->execute();
        $isolationCmd = isset($isolationLevel)
            ? $this->setTransactionIsolationLevel($isolationLevel)
            : resolve(true);
        return allInOrder([$beginCmd, $isolationCmd]);
    }

    /**
     * Commit transaction
     * @return LazyPromiseInterface
     */
    public function commitTransaction()
    {
        return $this->createCommand("COMMIT")->execute();
    }

    /**
     * Rolls back whole transaction
     * @return LazyPromiseInterface
     */
    public function rollBackTransaction() {
        return $this->createCommand("ROLLBACK")->execute();
    }

    /**
     * Creates a new savepoint.
     * @param string $name the savepoint name
     * @return LazyPromiseInterface
     */
    public function createSavepoint($name)
    {
        return $this->createCommand("SAVEPOINT $name")->execute();
    }

    /**
     * Releases an existing savepoint.
     * @param string $name the savepoint name
     * @return LazyPromiseInterface
     */
    public function releaseSavepoint($name)
    {
        return $this->createCommand("RELEASE SAVEPOINT $name")->execute();
    }

    /**
     * Rolls back to a previously created savepoint.
     * @param string $name the savepoint name
     * @return LazyPromiseInterface
     */
    public function rollBackSavepoint($name)
    {
        return $this->createCommand("ROLLBACK TO SAVEPOINT $name")->execute();
    }

    /**
     * Sets the isolation level of the current transaction.
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]], [[Transaction::REPEATABLE_READ]]
     * and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific syntax to be used
     * after `SET TRANSACTION ISOLATION LEVEL`.
     * @return LazyPromiseInterface
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    public function setTransactionIsolationLevel($level)
    {
        return $this->createCommand("SET TRANSACTION ISOLATION LEVEL $level")->execute();
    }

    /**
     * Create DB command
     * @param string|null $sql
     * @param array       $params
     * @return CommandInterface
     */
    public function createCommand($sql = null, $params = [])
    {
        return $this->db->createCommand($sql, $params, $this);
    }

    /**
     * Execute SQL statement string
     * @param string $sql Statement SQL string
     * @param array  $params Statement parameters
     * @param bool   $lazy Use lazy promise
     * @return ExtendedPromiseInterface|LazyPromiseInterface
     */
    public function executeSql($sql, $params = [], $lazy = true)
    {
        throw new NotSupportedException("SQL execution is not supported");
    }

    /**
     * Close connection
     */
    public function close()
    {
        $this->emit(static::EVENT_CLOSE, [$this]);
    }
}