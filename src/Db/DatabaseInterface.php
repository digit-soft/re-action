<?php

namespace Reaction\Db;

use Reaction\Base\ComponentAutoloadInterface;
use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\Cache\ExpiringCacheInterface;
use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Interface DatabaseInterface
 * @package Reaction\Db
 * @property string  $host
 * @property integer $port
 * @property string  $database
 * @property string  $username
 * @property string  $password
 * @property string  $tablePrefix
 * @property integer $schemaCacheDuration
 * @property bool    $schemaCacheEnable
 * @property array   $schemaCacheExclude
 * @property int     $queryCacheDuration
 * @property bool    $enableLogging
 * @property bool    $enableProfiling
 * @property bool    $enableSavepoint
 */
interface DatabaseInterface extends ComponentAutoloadInterface, ComponentInitBlockingInterface
{
    /**
     * Get DB driver name
     * @return string
     */
    public function getDriverName();

    /**
     * Get query builder
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder();

    /**
     * Get Schema
     * @return SchemaInterface
     */
    public function getSchema();

    /**
     * Create command
     * @param string|null              $sql
     * @param array                    $params
     * @param ConnectionInterface|null $connection
     * @return CommandInterface
     */
    public function createCommand($sql = null, $params = [], $connection = null);

    /**
     * Create ColumnSchemaInterface instance
     * @return ColumnSchemaInterface
     */
    public function createColumnSchema();

    /**
     * Get cache component
     * @return ExpiringCacheInterface|null
     */
    public function getCache();

    /**
     * Get dsn string for cache keys
     * @return string
     */
    public function getDsn();

    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $value string to be quoted
     * @return string the properly quoted string
     * @see http://php.net/manual/en/pdo.quote.php
     */
    public function quoteValue($value);

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName($name);

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name);

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     * Tokens enclosed within double curly brackets are treated as table names, while
     * tokens enclosed within double square brackets are column names. They will be quoted accordingly.
     * Also, the percentage character "%" at the beginning or ending of a table name will be replaced
     * with [[tablePrefix]].
     * @param string $sql the SQL to be quoted
     * @return string the quoted SQL
     */
    public function quoteSql($sql);

    /**
     * Execute SQL statement string
     * @param string $sql Statement SQL string
     * @param array  $params Statement parameters
     * @param bool   $lazy Use lazy promise
     * @return ExtendedPromiseInterface
     */
    public function executeSql($sql, $params = [], $lazy = true);

    /**
     * Create transaction for query isolation
     * @return TransactionInterface
     */
    public function createTransaction();

    /**
     * Get dedicated connection (Not used in shared pool)
     * @return ConnectionInterface
     */
    public function getDedicatedConnection() ;
}