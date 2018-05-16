<?php

namespace Reaction\Db;

use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Interface SchemaInterface
 * @package Reaction\Db
 */
interface SchemaInterface
{
    // The following are the supported abstract column data types.
    const TYPE_PK = 'pk';
    const TYPE_UPK = 'upk';
    const TYPE_BIGPK = 'bigpk';
    const TYPE_UBIGPK = 'ubigpk';
    const TYPE_CHAR = 'char';
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_TINYINT = 'tinyint';
    const TYPE_SMALLINT = 'smallint';
    const TYPE_INTEGER = 'integer';
    const TYPE_BIGINT = 'bigint';
    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_DATETIME = 'datetime';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIME = 'time';
    const TYPE_DATE = 'date';
    const TYPE_BINARY = 'binary';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_MONEY = 'money';
    const TYPE_JSON = 'json';


    /**
     * Refreshes the schema.
     * This method cleans up all cached table schemas so that they can be re-created later
     * to reflect the database schema change.
     * @return ExtendedPromiseInterface when finished
     */
    public function refresh();

    /**
     * @return QueryBuilderInterface the query builder for this connection.
     */
    public function getQueryBuilder();

    /**
     * Creates a query builder for the database.
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     * @return QueryBuilderInterface query builder instance
     */
    public function createQueryBuilder();

    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * This method may be overridden by child classes to create a DBMS-specific column schema builder.
     *
     * @param string $type type of the column. See [[ColumnSchemaBuilder::$type]].
     * @param int|string|array $length length or precision of the column. See [[ColumnSchemaBuilder::$length]].
     * @return ColumnSchemaBuilder column schema builder instance
     */
    public function createColumnSchemaBuilder($type, $length = null);

    /**
     * Returns a server version as a string comparable by [[\version_compare()]].
     * @return string server version as a string.
     */
    public function getServerVersion();

    /**
     * Obtains the metadata for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return ExtendedPromiseInterface with TableSchema|null table metadata. `null` if the named table does not exist.
     */
    public function getTableSchema($name, $refresh = false);

    /**
     * Returns the metadata for all tables in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * @param bool $refresh whether to fetch the latest available table schemas. If this is `false`,
     * cached data may be returned if available.
     * @return ExtendedPromiseInterface with TableSchema[] the metadata for all tables in the database.
     * Each array element is an instance of [[TableSchema]] or its child class.
     */
    public function getTableSchemas($schema = '', $refresh = false);

    /**
     * Returns all schema names in the database, except system schemas.
     * @param bool $refresh whether to fetch the latest available schema names. If this is false,
     * schema names fetched previously (if available) will be returned.
     * @return string[] all schema names in the database, except system schemas.
     * @since 2.0.4
     */
    public function getSchemaNames($refresh = false);

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * If not empty, the returned table names will be prefixed with the schema name.
     * @param bool   $refresh whether to fetch the latest available table names. If this is false,
     * table names fetched previously (if available) will be returned.
     * @return ExtendedPromiseInterface with string[] all table names in the database.
     */
    public function getTableNames($schema = '', $refresh = false);


    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $str string to be quoted
     * @return string the properly quoted string
     * @see http://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue($str);

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains '(' or '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     * @see quoteSimpleTableName()
     */
    public function quoteTableName($name);

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains '(', '[[' or '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     * @see quoteSimpleColumnName()
     */
    public function quoteColumnName($name);

    /**
     * Quotes a simple table name for use in a query.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is already quoted, this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteSimpleTableName($name);

    /**
     * Quotes a simple column name for use in a query.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is already quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteSimpleColumnName($name);

    /**
     * Unquotes a simple table name.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is not quoted, this method will do nothing.
     * @param string $name table name.
     * @return string unquoted table name.
     */
    public function unquoteSimpleTableName($name);

    /**
     * Unquotes a simple column name.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is not quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name.
     * @return string unquoted column name.
     */
    public function unquoteSimpleColumnName($name);

    /**
     * Returns the actual name of a given table name.
     * This method will strip off curly brackets from the given table name
     * and replace the percentage character '%' with [[Connection::tablePrefix]].
     * @param string $name the table name to be converted
     * @return string the real name of the given table name
     */
    public function getRawTableName($name);

    /**
     * Init component
     * @return ExtendedPromiseInterface
     */
    public function initComponent();
}