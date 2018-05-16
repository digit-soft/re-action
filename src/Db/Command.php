<?php

namespace Reaction\Db;

use Reaction\Base\Component;
use Reaction\Db\Expressions\Expression;
use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Class Command
 * @package Reaction\Db
 * @property DatabaseInterface $db
 * @property string            $sql
 * @property string            $rawSql
 */
class Command extends Component implements CommandInterface
{
    /**
     * @var array the parameters (name => value) that are bound to the current PDO statement.
     * This property is maintained by methods such as [[bindValue()]]. It is mainly provided for logging purpose
     * and is used to generate [[rawSql]]. Do not modify it directly.
     */
    public $params = [];
    /**
     * @var string Default fetch mode
     */
    public $fetchMode = self::FETCH_MODE_ASSOC;
    /**
     * @var int Query cache duration
     */
    public $queryCacheDuration;

    /**
     * @var DatabaseInterface the DB that this command is associated with
     */
    protected $_db;
    /**
     * @var string the SQL statement that this command represents
     */
    protected $_sql;


    /**
     * Enables query cache for this command.
     * @param int $duration the number of seconds that query result of this command can remain valid in the cache.
     * If this is not set, the value of [[DatabaseInterface::queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @return $this the command object itself
     */
    public function cache($duration = null)
    {
        $this->queryCacheDuration = $duration === null ? $this->db->queryCacheDuration : $duration;
        return $this;
    }

    /**
     * Get database
     * @return DatabaseInterface
     */
    public function getDb() {
        return $this->_db;
    }

    /**
     * Set database
     * @param DatabaseInterface|string|array $database
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    public function setDb($database) {
        if ($database instanceof DatabaseInterface) {
            $db = $database;
        } elseif (is_string($database) && \Reaction::$app->has($database)) {
            $db = \Reaction::$app->get($database);
        } else {
            $db = \Reaction::create($database);
        }
        $this->_db = $db;
    }

    /**
     * Returns the SQL statement for this command.
     * @return string the SQL statement to be executed
     */
    public function getSql()
    {
        return $this->_sql;
    }

    /**
     * Specifies the SQL statement to be executed. The SQL statement will be quoted using [[Connection::quoteSql()]].
     * The previous SQL (if any) will be discarded, and [[params]] will be cleared as well. See [[reset()]]
     * for details.
     *
     * @param string $sql the SQL statement to be set.
     * @return $this this command instance
     * @see reset()
     * @see cancel()
     */
    public function setSql($sql)
    {
        if ($sql !== $this->_sql) {
            $this->reset();
            $this->_sql = $this->db->quoteSql($sql);
        }

        return $this;
    }

    /**
     * Specifies the SQL statement to be executed. The SQL statement will not be modified in any way.
     * The previous SQL (if any) will be discarded, and [[params]] will be cleared as well. See [[reset()]]
     * for details.
     *
     * @param string $sql the SQL statement to be set.
     * @return $this this command instance
     * @since 2.0.13
     * @see reset()
     * @see cancel()
     */
    public function setRawSql($sql)
    {
        if ($sql !== $this->_sql) {
            $this->reset();
            $this->_sql = $sql;
        }

        return $this;
    }

    /**
     * Returns the raw SQL by inserting parameter values into the corresponding placeholders in [[sql]].
     * Note that the return value of this method should mainly be used for logging purpose.
     * It is likely that this method returns an invalid SQL due to improper replacement of parameter placeholders.
     * @return string the raw SQL with parameter values inserted into the corresponding placeholders in [[sql]].
     */
    public function getRawSql()
    {
        if (empty($this->params)) {
            return $this->_sql;
        }
        $params = [];
        foreach ($this->params as $name => $value) {
            if (is_string($name) && strncmp(':', $name, 1)) {
                $name = ':' . $name;
            }
            if (is_string($value)) {
                $params[$name] = $this->db->quoteValue($value);
            } elseif (is_bool($value)) {
                $params[$name] = ($value ? 'TRUE' : 'FALSE');
            } elseif ($value === null) {
                $params[$name] = 'NULL';
            } elseif ((!is_object($value) && !is_resource($value)) || $value instanceof Expression) {
                $params[$name] = $value;
            }
        }
        if (!isset($params[1])) {
            return strtr($this->_sql, $params);
        }
        $sql = '';
        foreach (explode('?', $this->_sql) as $i => $part) {
            $sql .= (isset($params[$i]) ? $params[$i] : '') . $part;
        }

        return $sql;
    }

    /**
     * Binds a value to a parameter.
     * @param string|int $name Parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form `:name`. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter
     * @return $this the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value)
    {
        $this->params[$name] = $value;

        return $this;
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to [[bindValue()]] except that it binds multiple values at a time.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values,
     * e.g. `[':name' => 'John', ':age' => 25]`.
     * @return $this the current command being executed
     */
    public function bindValues($values)
    {
        if (empty($values)) {
            return $this;
        }

        foreach ($values as $name => $value) {
            $this->params[$name] = $value;
        }

        return $this;
    }

    /**
     * Fetch results
     * @param array  $results
     * @param string $fetchMethod
     * @param string $fetchMode
     * @return array|null|object
     * @internal
     */
    public function fetchResults($results, $fetchMethod = self::FETCH_ALL, $fetchMode = self::FETCH_MODE_ASSOC) {
        $data = [];
        if ($fetchMethod === '') {
            return new DataReader($this, $results);
        }
        if (empty($results)) {
            return $fetchMethod === static::FETCH_FIELD ? null : [];
        } elseif (in_array($fetchMethod, [static::FETCH_FIELD, static::FETCH_ROW])) {
            $firstRow = reset($results);
            return $this->fetchResultsRow($firstRow, $fetchMethod, $fetchMode);
        }
        foreach ($results as $row) {
            $dataRow = $this->fetchResultsRow($row, $fetchMethod, $fetchMode);
            if ($dataRow === null) {
                continue;
            }
            $data[] = $dataRow;
        }
        return $data;
    }

    /**
     * Fetch results row
     * @param array  $row
     * @param string $fetchMethod
     * @param string $fetchMode
     * @param int    $colIndex
     * @return array|null|object
     * @internal
     */
    public function fetchResultsRow($row = [], $fetchMethod = self::FETCH_ALL, $fetchMode = self::FETCH_MODE_ASSOC, $colIndex = 0) {
        if (in_array($fetchMethod, [static::FETCH_ALL, static::FETCH_ROW])) {
            return $fetchMode === static::FETCH_MODE_OBJECT ? (object)$row : $row;
        } elseif (in_array($fetchMethod, [static::FETCH_COLUMN, static::FETCH_FIELD]) && $colIndex < count($row)) {
            $rowIndexed = array_values($row);
            return $rowIndexed[$colIndex];
        }
        return null;
    }




    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing a SQL query that returns result set, such as `SELECT`.
     * @return ExtendedPromiseInterface with DataReader the reader object for fetching the query result
     */
    public function query()
    {
        return $this->queryInternal('');
    }

    /**
     * Executes the SQL statement and returns ALL rows at once.
     * @param int $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return ExtendedPromiseInterface with all rows of the query result. Each array element is an array representing a row of data.
     * An empty array is returned if the query results in nothing.
     */
    public function queryAll($fetchMode = null)
    {
        return $this->queryInternal(static::FETCH_ALL, $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the first row of the result.
     * This method is best used when only the first row of result is needed for a query.
     * @param int $fetchMode the result fetch mode. Please refer to [PHP manual](http://php.net/manual/en/pdostatement.setfetchmode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return ExtendedPromiseInterface with the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function queryOne($fetchMode = null)
    {
        return $this->queryInternal(static::FETCH_ROW, $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This method is best used when only a single value is needed for a query.
     * @return ExtendedPromiseInterface the value of the first column in the first row of the query result.
     * False is returned if there is no value.
     */
    public function queryScalar()
    {
        return $this->queryInternal(static::FETCH_FIELD)->then(
            function($result) {
                if (is_resource($result) && get_resource_type($result) === 'stream') {
                    return stream_get_contents($result);
                }
                return $result;
            }
        );
    }

    /**
     * Executes the SQL statement and returns the first column of the result.
     * This method is best used when only the first column of result (i.e. the first element in each row)
     * is needed for a query.
     * @return ExtendedPromiseInterface with the first column of the query result. Empty array is returned if the query results in nothing.
     */
    public function queryColumn()
    {
        return $this->queryInternal(static::FETCH_COLUMN);
    }

    /**
     * Performs the actual DB query of a SQL statement.
     * @param string $fetchMethod method of PDOStatement to be called
     * @param int    $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return ExtendedPromiseInterface with the method execution result
     */
    protected function queryInternal($fetchMethod, $fetchMode = null)
    {
        $fetchMode = isset($fetchMode) ? $fetchMode : $this->fetchMode;

        $self = $this;
        return $this->internalExecute($this->sql, $this->params)->then(
            function($results) use ($self, $fetchMethod, $fetchMode) {
                return $self->fetchResults($results, $fetchMethod, $fetchMode);
            }
        );
    }

    /**
     * Execute SQL statement internally
     * @param string $sql
     * @param array  $params
     * @return ExtendedPromiseInterface
     */
    protected function internalExecute($sql, $params = []) {
        return $this->db->executeSql($sql, $params);
    }

    /**
     * Resets command properties to their initial state.
     */
    protected function reset()
    {
        $this->_sql = null;
        //$this->_pendingParams = [];
        $this->params = [];
        //$this->_refreshTableName = null;
        //$this->_isolationLevel = false;
        //$this->_retryHandler = null;
    }
}