<?php

namespace Reaction\Db;

use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Interface CommandInterface
 * @package Reaction\Db
 * @property DatabaseInterface $db
 * @property array             $params
 */
interface CommandInterface
{
    const FETCH_MODE_OBJECT = 'object';
    const FETCH_MODE_ASSOC  = 'assoc';

    const FETCH_ALL         = 'fetch_all';
    const FETCH_ROW         = 'fetch_row';
    const FETCH_FIELD       = 'fetch_field';
    const FETCH_COLUMN      = 'fetch_column';


    /**
     * Enables query cache for this command.
     * @param int $duration the number of seconds that query result of this command can remain valid in the cache.
     * If this is not set, the value of [[DatabaseInterface::queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @return $this the command object itself
     */
    public function cache($duration = null);


    /**
     * Returns the SQL statement for this command.
     * @return string the SQL statement to be executed
     */
    public function getSql();

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
    public function setSql($sql);

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
    public function setRawSql($sql);

    /**
     * Returns the raw SQL by inserting parameter values into the corresponding placeholders in [[sql]].
     * Note that the return value of this method should mainly be used for logging purpose.
     * It is likely that this method returns an invalid SQL due to improper replacement of parameter placeholders.
     * @return string the raw SQL with parameter values inserted into the corresponding placeholders in [[sql]].
     */
    public function getRawSql();

    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing a SQL query that returns result set, such as `SELECT`.
     * @return ExtendedPromiseInterface with DataReader the reader object for fetching the query result
     */
    public function query();

    /**
     * Executes the SQL statement and returns ALL rows at once.
     * @param int $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return ExtendedPromiseInterface with all rows of the query result. Each array element is an array representing a row of data.
     * An empty array is returned if the query results in nothing.
     */
    public function queryAll($fetchMode = null);

    /**
     * Executes the SQL statement and returns the first row of the result.
     * This method is best used when only the first row of result is needed for a query.
     * @param int $fetchMode the result fetch mode. Please refer to [PHP manual](http://php.net/manual/en/pdostatement.setfetchmode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return ExtendedPromiseInterface with the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function queryOne($fetchMode = null);

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This method is best used when only a single value is needed for a query.
     * @return ExtendedPromiseInterface the value of the first column in the first row of the query result.
     * False is returned if there is no value.
     */
    public function queryScalar();

    /**
     * Executes the SQL statement and returns the first column of the result.
     * This method is best used when only the first column of result (i.e. the first element in each row)
     * is needed for a query.
     * @return ExtendedPromiseInterface with the first column of the query result. Empty array is returned if the query results in nothing.
     */
    public function queryColumn();

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
    public function bindValue($name, $value);

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to [[bindValue()]] except that it binds multiple values at a time.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values,
     * e.g. `[':name' => 'John', ':age' => 25]`.
     * @return $this the current command being executed
     */
    public function bindValues($values);

    /**
     * Fetch results
     * @param array  $results
     * @param string $fetchMethod
     * @param string $fetchMode
     * @return array|null|object
     * @internal
     */
    public function fetchResults($results, $fetchMethod = self::FETCH_ALL, $fetchMode = self::FETCH_MODE_ASSOC);

    /**
     * Fetch results row
     * @param array  $row
     * @param string $fetchMethod
     * @param string $fetchMode
     * @param int    $colIndex
     * @return array|null|object
     * @internal
     */
    public function fetchResultsRow($row = [], $fetchMethod = self::FETCH_ALL, $fetchMode = self::FETCH_MODE_ASSOC, $colIndex = 0);

    /**
     * Executes the SQL statement.
     * This method should only be used for executing non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     * @return ExtendedPromiseInterface
     */
    public function execute();

    /**
     * Creates an INSERT command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->insert('user', [
     *     'name' => 'Sam',
     *     'age' => 30,
     * ])->execute();
     * ```
     *
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array|\Reaction\Db\Query $columns the column data (name => value) to be inserted into the table or instance
     * of [[Reaction\Db\Query|Query]] to perform INSERT INTO ... SELECT SQL statement.
     * @return ExtendedPromiseInterface with $this the command object itself
     */
    public function insert($table, $columns);
}