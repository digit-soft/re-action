<?php

namespace Reaction\Db;

use function PHPSTORM_META\elementType;
use Reaction\Base\Component;
use Reaction\Db\Expressions\Expression;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

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
     * @var ConnectionInterface the DB connection that this command is associated with. Used for transactions
     */
    public $connection;
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
     * @var string Table name marked for schema refresh
     */
    protected $_refreshTableName;


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
     * Set connection to use with
     * @param ConnectionInterface|TransactionInterface|null $connection
     * @return $this this command instance
     */
    public function setConnection($connection = null)
    {
        if ($connection instanceof TransactionInterface) {
            $connection = $connection->getConnection();
        }
        $this->connection = $connection;
        //Remove connection instance on close
        $this->connection->once(ConnectionInterface::EVENT_CLOSE, function() {
            $this->connection = null;
        });
        return $this;
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
     * @return $this the command object itself
     */
    public function insert($table, $columns)
    {
        $params = [];
        $sql = $this->db->getQueryBuilder()->insert($table, $columns, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a batch INSERT command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ])->execute();
     * ```
     *
     * The method will properly escape the column names, and quote the values to be inserted.
     *
     * Note that the values in each row must match the corresponding column names.
     *
     * Also note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names
     * @param array|\Generator $rows the rows to be batch inserted into the table
     * @return $this the command object itself
     */
    public function batchInsert($table, $columns, $rows)
    {
        $table = $this->db->quoteSql($table);
        $columns = array_map(function ($column) {
            return $this->db->quoteSql($column);
        }, $columns);

        $params = [];
        $sql = $this->db->getQueryBuilder()->batchInsert($table, $columns, $rows, $params);

        $this->setRawSql($sql);
        $this->bindValues($params);

        return $this;
    }

    /**
     * Creates a command to insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or update them if they do.
     *
     * For example,
     *
     * ```php
     * $sql = $queryBuilder->upsert('pages', [
     *     'name' => 'Front page',
     *     'url' => 'http://example.com/', // url is unique
     *     'visits' => 0,
     * ], [
     *     'visits' => new \Reaction\Db\Expressions\Expression('visits + 1'),
     * ], $params);
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * @param string $table the table that new rows will be inserted into/updated in.
     * @param array|Query $insertColumns the column data (name => value) to be inserted into the table or instance
     * of [[Query]] to perform `INSERT INTO ... SELECT` SQL statement.
     * @param array|bool $updateColumns the column data (name => value) to be updated if they already exist.
     * If `true` is passed, the column data will be updated to match the insert column data.
     * If `false` is passed, no update will be performed if the column data already exists.
     * @param array $params the parameters to be bound to the command.
     * @return $this the command object itself.
     */
    public function upsert($table, $insertColumns, $updateColumns = true, $params = [])
    {
        $sql = $this->db->getQueryBuilder()->upsert($table, $insertColumns, $updateColumns, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates an UPDATE command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->update('user', ['status' => 1], 'age > 30')->execute();
     * ```
     *
     * or with using parameter binding for the condition:
     *
     * ```php
     * $minAge = 30;
     * $connection->createCommand()->update('user', ['status' => 1], 'age > :minAge', [':minAge' => $minAge])->execute();
     * ```
     *
     * The method will properly escape the column names and bind the values to be updated.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param string|array $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array $params the parameters to be bound to the command
     * @return $this the command object itself
     */
    public function update($table, $columns, $condition = '', $params = [])
    {
        $sql = $this->db->getQueryBuilder()->update($table, $columns, $condition, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a DELETE command.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->delete('user', 'status = 0')->execute();
     * ```
     *
     * or with using parameter binding for the condition:
     *
     * ```php
     * $status = 0;
     * $connection->createCommand()->delete('user', 'status = :status', [':status' => $status])->execute();
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table where the data will be deleted from.
     * @param string|array $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array $params the parameters to be bound to the command
     * @return $this the command object itself
     */
    public function delete($table, $condition = '', $params = [])
    {
        $sql = $this->db->getQueryBuilder()->delete($table, $condition, $params);

        return $this->setSql($sql)->bindValues($params);
    }



    /**
     * Creates a SQL command for creating a new DB table.
     *
     * The columns in the new table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     * The method [[QueryBuilder::getColumnType()]] will be called
     * to convert the abstract column types to physical ones. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * inserted into the generated SQL.
     *
     * @param string $table the name of the table to be created. The name will be properly quoted by the method.
     * @param array $columns the columns (name => definition) in the new table.
     * @param string $options additional SQL fragment that will be appended to the generated SQL.
     * @return $this the command object itself
     */
    public function createTable($table, $columns, $options = null)
    {
        $sql = $this->db->getQueryBuilder()->createTable($table, $columns, $options);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for renaming a DB table.
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function renameTable($table, $newName)
    {
        $sql = $this->db->getQueryBuilder()->renameTable($table, $newName);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a DB table.
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropTable($table)
    {
        $sql = $this->db->getQueryBuilder()->dropTable($table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function truncateTable($table)
    {
        $sql = $this->db->getQueryBuilder()->truncateTable($table);

        return $this->setSql($sql);
    }



    /**
     * Creates a SQL command for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. [[\yii\db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     * @return $this the command object itself
     */
    public function addColumn($table, $column, $type)
    {
        $sql = $this->db->getQueryBuilder()->addColumn($table, $column, $type);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropColumn($table, $column)
    {
        $sql = $this->db->getQueryBuilder()->dropColumn($table, $column);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function renameColumn($table, $oldName, $newName)
    {
        $sql = $this->db->getQueryBuilder()->renameColumn($table, $oldName, $newName);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the column type. [[\yii\db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     * @return $this the command object itself
     */
    public function alterColumn($table, $column, $type)
    {
        $sql = $this->db->getQueryBuilder()->alterColumn($table, $column, $type);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }


    /**
     * Creates a SQL command for adding a primary key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return $this the command object itself.
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        $sql = $this->db->getQueryBuilder()->addPrimaryKey($name, $table, $columns);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return $this the command object itself
     */
    public function dropPrimaryKey($name, $table)
    {
        $sql = $this->db->getQueryBuilder()->dropPrimaryKey($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas.
     * @param string $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return $this the command object itself
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $sql = $this->db->getQueryBuilder()->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropForeignKey($name, $table)
    {
        $sql = $this->db->getQueryBuilder()->dropForeignKey($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
     * by commas. The column names will be properly quoted by the method.
     * @param bool $unique whether to add UNIQUE constraint on the created index.
     * @return $this the command object itself
     */
    public function createIndex($name, $table, $columns, $unique = false)
    {
        $sql = $this->db->getQueryBuilder()->createIndex($name, $table, $columns, $unique);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropIndex($name, $table)
    {
        $sql = $this->db->getQueryBuilder()->dropIndex($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding an unique constraint to an existing table.
     * @param string $name the name of the unique constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the unique constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas.
     * The name will be properly quoted by the method.
     * @return $this the command object itself.
     */
    public function addUnique($name, $table, $columns)
    {
        $sql = $this->db->getQueryBuilder()->addUnique($name, $table, $columns);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping an unique constraint.
     * @param string $name the name of the unique constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose unique constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return $this the command object itself.
     */
    public function dropUnique($name, $table)
    {
        $sql = $this->db->getQueryBuilder()->dropUnique($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     * @param string $name the name of the check constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     * @return $this the command object itself.
     */
    public function addCheck($name, $table, $expression)
    {
        $sql = $this->db->getQueryBuilder()->addCheck($name, $table, $expression);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a check constraint.
     * @param string $name the name of the check constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose check constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return $this the command object itself.
     */
    public function dropCheck($name, $table)
    {
        $sql = $this->db->getQueryBuilder()->dropCheck($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a default value constraint to an existing table.
     * @param string $name the name of the default value constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the default value constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $column the name of the column to that the constraint will be added on.
     * The name will be properly quoted by the method.
     * @param mixed $value default value.
     * @return $this the command object itself.
     */
    public function addDefaultValue($name, $table, $column, $value)
    {
        $sql = $this->db->getQueryBuilder()->addDefaultValue($name, $table, $column, $value);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a default value constraint.
     * @param string $name the name of the default value constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose default value constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return $this the command object itself.
     */
    public function dropDefaultValue($name, $table)
    {
        $sql = $this->db->getQueryBuilder()->dropDefaultValue($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }


    /**
     * Creates a SQL command for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or 1.
     * @param string $table the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have a value 1.
     * @return $this the command object itself
     */
    public function resetSequence($table, $value = null)
    {
        $sql = $this->db->getQueryBuilder()->resetSequence($table, $value);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for enabling or disabling integrity check.
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema name of the tables. Defaults to empty string, meaning the current
     * or default schema.
     * @param string $table the table name.
     * @return $this the command object itself
     */
    public function checkIntegrity($check = true, $schema = '', $table = '')
    {
        $sql = $this->db->getQueryBuilder()->checkIntegrity($check, $schema, $table);

        return $this->setSql($sql);
    }



    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function addCommentOnColumn($table, $column, $comment)
    {
        $sql = $this->db->getQueryBuilder()->addCommentOnColumn($table, $column, $comment);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function addCommentOnTable($table, $comment)
    {
        $sql = $this->db->getQueryBuilder()->addCommentOnTable($table, $comment);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for dropping comment from column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropCommentFromColumn($table, $column)
    {
        $sql = $this->db->getQueryBuilder()->dropCommentFromColumn($table, $column);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Builds a SQL command for dropping comment from table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropCommentFromTable($table)
    {
        $sql = $this->db->getQueryBuilder()->dropCommentFromTable($table);

        return $this->setSql($sql);
    }



    /**
     * Creates a SQL View.
     *
     * @param string $viewName the name of the view to be created.
     * @param string|Query $subquery the select statement which defines the view.
     * This can be either a string or a [[Query]] object.
     * @return $this the command object itself.
     */
    public function createView($viewName, $subquery)
    {
        $sql = $this->db->getQueryBuilder()->createView($viewName, $subquery);

        return $this->setSql($sql)->requireTableSchemaRefresh($viewName);
    }

    /**
     * Drops a SQL View.
     *
     * @param string $viewName the name of the view to be dropped.
     * @return $this the command object itself.
     */
    public function dropView($viewName)
    {
        $sql = $this->db->getQueryBuilder()->dropView($viewName);

        return $this->setSql($sql)->requireTableSchemaRefresh($viewName);
    }


    /**
     * Executes the SQL statement.
     * This method should only be used for executing non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     * @param bool $lazy Use LazyPromise
     * @return ExtendedPromiseInterface|LazyPromiseInterface
     */
    public function execute($lazy = true)
    {
        $sql = $this->getSql();
        $rawSql = $this->getRawSql();

        if ($sql == '') {
            return reject(false);
        }

        $execPromise = $this->internalExecute($rawSql, [], $lazy);
        return $execPromise instanceof LazyPromiseInterface
            ? $execPromise->thenLazy(function() { return $this->refreshTableSchema(); })
            : $execPromise->then(function() { return $this->refreshTableSchema(); });
    }



    /**
     * Logs the current database query if query logging is enabled and returns
     * the profiling token if profiling is enabled.
     * @param string $category the log category.
     * @return array array of two elements, the first is boolean of whether profiling is enabled or not.
     * The second is the rawSql if it has been created.
     */
    protected function logQuery($category)
    {
        if ($this->db->enableLogging) {
            $rawSql = $this->getRawSql();
            $message = sprintf("SQL: \"%s\"\nCategory: %s", $rawSql, $category);
            \Reaction::info($message);
        }
        if (!$this->db->enableProfiling) {
            return [false, isset($rawSql) ? $rawSql : null];
        }

        return [true, isset($rawSql) ? $rawSql : $this->getRawSql()];
    }

    /**
     * Marks a specified table schema to be refreshed after command execution.
     * @param string $name name of the table, which schema should be refreshed.
     * @return $this this command instance
     */
    protected function requireTableSchemaRefresh($name)
    {
        $this->_refreshTableName = $name;
        return $this;
    }

    /**
     * Refreshes table schema, which was marked by [[requireTableSchemaRefresh()]].
     * @return ExtendedPromiseInterface to know when finished
     */
    protected function refreshTableSchema()
    {
        if ($this->_refreshTableName !== null) {
            return $this->db->getSchema()->refreshTableMetadata($this->_refreshTableName);
        }
        return resolve(true);
    }



    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing a SQL query that returns result set, such as `SELECT`.
     * @return LazyPromiseInterface with DataReader the reader object for fetching the query result
     */
    public function query()
    {
        return $this->queryInternal('');
    }

    /**
     * Executes the SQL statement and returns ALL rows at once.
     * @param int $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return LazyPromiseInterface with all rows of the query result. Each array element is an array representing a row of data.
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
     * @return LazyPromiseInterface with the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function queryOne($fetchMode = null)
    {
        return $this->queryInternal(static::FETCH_ROW, $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This method is best used when only a single value is needed for a query.
     * @return LazyPromiseInterface the value of the first column in the first row of the query result.
     * False is returned if there is no value.
     */
    public function queryScalar()
    {
        return $this->queryInternal(static::FETCH_FIELD)->thenLazy(
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
     * @return LazyPromiseInterface with the first column of the query result. Empty array is returned if the query results in nothing.
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
     * @param bool   $lazy Use LazyPromise
     * @return ExtendedPromiseInterface|LazyPromiseInterface with the method execution result
     */
    protected function queryInternal($fetchMethod, $fetchMode = null, $lazy = true)
    {
        $fetchMode = isset($fetchMode) ? $fetchMode : $this->fetchMode;
        list($profile, $rawSql) = $this->logQuery(__METHOD__);

        $self = $this;
        $execPromise = $this->internalExecute($this->sql, $this->params, $lazy);
        if ($execPromise instanceof LazyPromiseInterface) {
            return $execPromise->thenLazy(
                function($results) use ($fetchMethod, $fetchMode) {
                    return $this->fetchResults($results, $fetchMethod, $fetchMode);
                }
            );
        } else {
            $profileId = $profile ? \Reaction::$app->logger->profile('Query: ' . $rawSql) : null;
            return $execPromise->then(
                function($results) use ($self, $fetchMethod, $fetchMode) {
                    return $self->fetchResults($results, $fetchMethod, $fetchMode);
                }
            )->then(
                function($data) use ($profileId) {
                    \Reaction::$app->logger->profileEnd($profileId);
                    return $data;
                },
                function($error = null) use ($profileId) {
                    \Reaction::$app->logger->profileEnd($profileId);
                    return reject($error);
                }
            );
        }
    }

    /**
     * Execute SQL statement internally
     * @param string $sql
     * @param array  $params
     * @param bool   $lazy
     * @return ExtendedPromiseInterface
     */
    protected function internalExecute($sql, $params = [], $lazy = true)
    {
        return isset($this->connection)
            ? $this->connection->executeSql($sql, $params, $lazy)
            : $this->db->executeSql($sql, $params, $lazy);
    }

    /**
     * Resets command properties to their initial state.
     */
    protected function reset()
    {
        $this->_sql = null;
        $this->params = [];
        $this->_refreshTableName = null;
        //$this->_isolationLevel = false;
    }
}