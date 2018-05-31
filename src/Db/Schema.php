<?php

namespace Reaction\Db;

use React\Promise\PromiseInterface;
use Reaction\Base\BaseObject;
use Reaction\Db\Constraints\CheckConstraint;
use Reaction\Db\Constraints\Constraint;
use Reaction\Db\Constraints\DefaultValueConstraint;
use Reaction\Db\Constraints\ForeignKeyConstraint;
use Reaction\Db\Constraints\IndexConstraint;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\all;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * Class Schema
 * @package Reaction\Db
 * @method  getViewNames(string $schema = "", bool $refresh = false)
 */
class Schema extends BaseObject implements SchemaInterface
{
    /**
     * Schema cache version, to detect incompatibilities in cached values when the
     * data format of the cache changes.
     */
    const SCHEMA_CACHE_VERSION = 1;

    /**
     * @var DatabaseInterface
     */
    public $db;
    /**
     * @var string the default schema name used for the current session.
     */
    public $defaultSchema;
    /**
     * @var array Table metadata type those are implemented by driver
     */
    public $tableMetaImplemented = [
        self::META_SCHEMA,
        self::META_PK,
        self::META_FK,
        self::META_INDEXES,
        self::META_UNIQUES,
        self::META_CHECKS,
        self::META_DEFAULTS,
    ];


    /**
     * @var array list of ALL schema names in the database, except system schemas
     */
    protected $_schemaNames;
    /**
     * @var array list of ALL table names in the database
     */
    protected $_tableNames = [];
    /**
     * @var array list of all table metadata in the database for sync usage
     */
    protected $_tableMetadata = [];
    /**
     * @var string|string[] character used to quote schema, table, etc. names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     */
    protected $tableQuoteCharacter = "'";
    /**
     * @var string|string[] character used to quote column names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     */
    protected $columnQuoteCharacter = '"';
    /**
     * @var string server version as a string.
     */
    protected $_serverVersion;
    /**
     * @var QueryBuilderInterface DB query builder
     */
    protected $_builder;


    /**
     * Refreshes the schema.
     * This method cleans up all cached table schemas so that they can be re-created later
     * to reflect the database schema change.
     * @return ExtendedPromiseInterface when finished
     */
    public function refresh()
    {
        return $this->refreshTablesMetadata();
    }

    /**
     * @return QueryBuilderInterface the query builder for this connection.
     */
    public function getQueryBuilder()
    {
        if ($this->_builder === null) {
            $this->_builder = $this->createQueryBuilder();
        }

        return $this->_builder;
    }

    /**
     * Creates a query builder for the database.
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     * @return QueryBuilderInterface query builder instance
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder(['db' => $this->db]);
    }

    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * This method may be overridden by child classes to create a DBMS-specific column schema builder.
     *
     * @param string $type type of the column. See [[ColumnSchemaBuilder::$type]].
     * @param int|string|array $length length or precision of the column. See [[ColumnSchemaBuilder::$length]].
     * @return ColumnSchemaBuilder column schema builder instance
     */
    public function createColumnSchemaBuilder($type, $length = null)
    {
        return new ColumnSchemaBuilder($type, $length);
    }

    /**
     * Returns a server version as a string comparable by [[\version_compare()]].
     * @return string server version as a string.
     */
    public function getServerVersion()
    {
        throw new NotSupportedException(get_class($this) . ' does not support version obtaining.');
    }

    /**
     * @return bool whether this DBMS supports [savepoint](http://en.wikipedia.org/wiki/Savepoint).
     */
    public function supportsSavepoint()
    {
        return $this->db->enableSavepoint;
    }

    /**
     * Obtains the metadata for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return TableSchema|null table metadata. `null` if the named table does not exist.
     */
    public function getTableSchema($name, $refresh = false)
    {
        return $this->getTableMetadata($name, 'schema', $refresh);
    }


    /**
     * Obtains the metadata for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return ExtendedPromiseInterface with TableSchema table metadata. `null` if the named table does not exist.
     */
    public function getTableSchemaAsync($name, $refresh = false) {
        return $this->getTableMetadataRaw($name, 'schema', $refresh);
    }

    /**
     * Returns the metadata for all tables in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * @param bool $refresh whether to fetch the latest available table schemas. If this is `false`,
     * cached data may be returned if available.
     * @return ExtendedPromiseInterface with TableSchema[] the metadata for all tables in the database.
     * Each array element is an instance of [[TableSchema]] or its child class.
     */
    public function getTableSchemas($schema = '', $refresh = false)
    {
        return $this->getSchemaMetadata($schema, 'schema', $refresh);
    }

    /**
     * Returns all schema names in the database, except system schemas.
     * @param bool $refresh whether to fetch the latest available schema names. If this is false,
     * schema names fetched previously (if available) will be returned.
     * @return ExtendedPromiseInterface with string[] all schema names in the database, except system schemas.
     */
    public function getSchemaNames($refresh = false)
    {
        if ($this->_schemaNames === null || $refresh) {
            return $this->findSchemaNames()->then(
                function($names) use($refresh) {
                    $this->_schemaNames = $names;
                    return $names;
                }
            );
        }

        return resolve($this->_schemaNames);
    }

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * If not empty, the returned table names will be prefixed with the schema name.
     * @param bool   $refresh whether to fetch the latest available table names. If this is false,
     * table names fetched previously (if available) will be returned.
     * @return ExtendedPromiseInterface with string[] all table names in the database.
     */
    public function getTableNames($schema = '', $refresh = false)
    {
        if (!isset($this->_tableNames[$schema]) || $refresh) {
            return $this->findTableNames($schema)->then(
                function($names) use ($schema) {
                    $this->_tableNames[$schema] = $names;
                    return $names;
                }
            );
        }

        return resolve($this->_tableNames[$schema]);
    }

    /**
     * Resolves the table name and schema name (if any).
     * @param string $name the table name
     * @return TableSchema [[TableSchema]] with resolved table, schema, etc. names.
     */
    protected function resolveTableName($name)
    {
        throw new NotSupportedException(get_class($this) . ' does not support resolving table names.');
    }

    /**
     * Returns all schema names in the database, including the default one but not system schemas.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     * @return ExtendedPromiseInterface with array all schema names in the database, except system schemas.
     */
    protected function findSchemaNames()
    {
        throw new NotSupportedException(get_class($this) . ' does not support fetching all schema names.');
    }

    /**
     * Returns all table names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return ExtendedPromiseInterface with array all table names in the database. The names have NO schema name prefix.
     */
    protected function findTableNames($schema = '')
    {
        throw new NotSupportedException(get_class($this) . ' does not support fetching all table names.');
    }



    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $str string to be quoted
     * @return string the properly quoted string
     * @see http://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue($str)
    {
        return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains '(' or '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     * @see quoteSimpleTableName()
     */
    public function quoteTableName($name)
    {
        if (strpos($name, '(') !== false || strpos($name, '{{') !== false) {
            return $name;
        }
        if (strpos($name, '.') === false) {
            return $this->quoteSimpleTableName($name);
        }
        $parts = explode('.', $name);
        foreach ($parts as $i => $part) {
            $parts[$i] = $this->quoteSimpleTableName($part);
        }

        return implode('.', $parts);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains '(', '[[' or '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     * @see quoteSimpleColumnName()
     */
    public function quoteColumnName($name)
    {
        if (strpos($name, '(') !== false || strpos($name, '[[') !== false) {
            return $name;
        }
        if (($pos = strrpos($name, '.')) !== false) {
            $prefix = $this->quoteTableName(substr($name, 0, $pos)) . '.';
            $name = substr($name, $pos + 1);
        } else {
            $prefix = '';
        }
        if (strpos($name, '{{') !== false) {
            return $name;
        }

        return $prefix . $this->quoteSimpleColumnName($name);
    }

    /**
     * Quotes a simple table name for use in a query.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is already quoted, this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteSimpleTableName($name)
    {
        if (is_string($this->tableQuoteCharacter)) {
            $startingCharacter = $endingCharacter = $this->tableQuoteCharacter;
        } else {
            list($startingCharacter, $endingCharacter) = $this->tableQuoteCharacter;
        }
        return strpos($name, $startingCharacter) !== false ? $name : $startingCharacter . $name . $endingCharacter;
    }

    /**
     * Quotes a simple column name for use in a query.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is already quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteSimpleColumnName($name)
    {
        if (is_string($this->tableQuoteCharacter)) {
            $startingCharacter = $endingCharacter = $this->columnQuoteCharacter;
        } else {
            list($startingCharacter, $endingCharacter) = $this->columnQuoteCharacter;
        }
        return $name === '*' || strpos($name, $startingCharacter) !== false ? $name : $startingCharacter . $name . $endingCharacter;
    }

    /**
     * Unquotes a simple table name.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is not quoted, this method will do nothing.
     * @param string $name table name.
     * @return string unquoted table name.
     */
    public function unquoteSimpleTableName($name)
    {
        if (is_string($this->tableQuoteCharacter)) {
            $startingCharacter = $this->tableQuoteCharacter;
        } else {
            $startingCharacter = $this->tableQuoteCharacter[0];
        }
        return strpos($name, $startingCharacter) === false ? $name : substr($name, 1, -1);
    }

    /**
     * Unquotes a simple column name.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is not quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name.
     * @return string unquoted column name.
     */
    public function unquoteSimpleColumnName($name)
    {
        if (is_string($this->columnQuoteCharacter)) {
            $startingCharacter = $this->columnQuoteCharacter;
        } else {
            $startingCharacter = $this->columnQuoteCharacter[0];
        }
        return strpos($name, $startingCharacter) === false ? $name : substr($name, 1, -1);
    }

    /**
     * Returns the actual name of a given table name.
     * This method will strip off curly brackets from the given table name
     * and replace the percentage character '%' with [[Connection::tablePrefix]].
     * @param string $name the table name to be converted
     * @return string the real name of the given table name
     */
    public function getRawTableName($name)
    {
        if (strpos($name, '{{') !== false) {
            $name = preg_replace('/\\{\\{(.*?)\\}\\}/', '\1', $name);

            return str_replace('%', $this->db->tablePrefix, $name);
        }

        return $name;
    }

    /**
     * Refresh table schema
     * @param string $tableName
     * @param bool   $nameIsRaw
     * @return ExtendedPromiseInterface
     */
    public function refreshTableSchema($tableName, $nameIsRaw = false) {
        return $this->refreshTableMetadata($tableName, $nameIsRaw);
    }

    /**
     * Refresh table schema, indexes, primary key and other metadata
     * @param string $tableName
     * @param bool   $nameIsRaw
     * @return ExtendedPromiseInterface
     */
    public function refreshTableMetadata($tableName, $nameIsRaw = false) {
        $rawName = $nameIsRaw ? $tableName : $this->getRawTableName($tableName);
        $metaTypes = $this->tableMetaImplemented;
        if (isset($this->_tableMetadata[$rawName])) {
            unset($this->_tableMetadata[$rawName]);
        }
        foreach ($metaTypes as $metaType) {
            $promises[] = $this->getTableMetadataRaw($rawName, $metaType, true)->otherwise(function() { return true; });
        }
        return !empty($promises) ? all($promises) : resolve(true);
    }

    /**
     * Executes the INSERT command, returning primary key values.
     * @param string                   $table the table that new rows will be inserted into.
     * @param array                    $columns the column data (name => value) to be inserted into the table.
     * @param ConnectionInterface|null $connection
     * @return ExtendedPromiseInterface with array|false primary key values or false if the command fails
     */
    public function insert($table, $columns, $connection = null)
    {
        $command = $this->db->createCommand()->insert($table, $columns);
        if (isset($connection)) {
            $command->setConnection($connection);
        }
        return $command->execute()->then(
            function() use ($table, $columns) {
                $tableSchema = $this->getTableSchema($table);
                $result = [];
                foreach ($tableSchema->primaryKey as $name) {
                    if ($tableSchema->columns[$name]->autoIncrement) {
                        $result[$name] = $this->getLastInsertID($tableSchema->sequenceName);
                        break;
                    }

                    $result[$name] = isset($columns[$name]) ? $columns[$name] : $tableSchema->columns[$name]->defaultValue;
                }

                return $result;
            }
        );
    }

    /**
     * Get last inserted ID
     * NOT SUPPORTED!
     * @param string $sequenceName
     * @return null
     */
    public function getLastInsertID($sequenceName = '') {
        return null;
    }



    /**
     * Get table metadata
     * @param string $name
     * @param string $type
     * @param bool   $refresh
     * @return TableSchema|Constraint|Constraint[]|IndexConstraint[]|ForeignKeyConstraint[]|DefaultValueConstraint[]|CheckConstraint[]|null
     */
    protected function getTableMetadata($name, $type = self::META_SCHEMA, $refresh = false)
    {
        $rawName = $this->getRawTableName($name);
        if ($refresh) {
            $this->getTableMetadataRaw($rawName, $type, true);
        }
        if (isset($this->_tableMetadata[$rawName][$type])) {
            return $this->_tableMetadata[$rawName][$type];
        }
        return null;
    }

    /**
     * @param string $name
     * @param string $type
     * @param bool   $refresh
     * @return ExtendedPromiseInterface
     * @deprecated Old async function
     */
    protected function getTableMetadataAsync($name, $type, $refresh = false)
    {
        $rawName = $this->getRawTableName($name);
        $metadata = $this->getTableMetadata($name, $type);
        if ($metadata === null || $refresh) {
            return $this->getTableMetadataRaw($rawName, $type, true);
        }
        return resolve($metadata);
    }

    /**
     * Get table metadata from DB
     * @param string $name
     * @param string $type
     * @param bool   $refresh
     * @return ExtendedPromiseInterface
     */
    protected function getTableMetadataRaw($name, $type, $refresh = false)
    {
        /** @var ExtendedPromiseInterface $promise */
        $promise = $this->{'loadTable' . ucfirst($type)}($name);
        if ($refresh) {
            $promise->then(function($data) use ($name, $type) {
                $this->_tableMetadata[$name] = isset($this->_tableMetadata[$name]) ? $this->_tableMetadata[$name] : [];
                $this->_tableMetadata[$name][$type] = $data;
                return $data;
            });
        }
        return $promise;
    }

    /**
     * Returns the metadata of the given type for all tables in the given schema.
     * This method will call a `'getTable' . ucfirst($type)` named method with the table name
     * and the refresh flag to obtain the metadata.
     * @param string $schema the schema of the metadata. Defaults to empty string, meaning the current or default schema name.
     * @param string $type metadata type.
     * @param bool $refresh whether to fetch the latest available table metadata. If this is `false`,
     * cached data may be returned if available.
     * @return ExtendedPromiseInterface with array array of metadata.
     */
    protected function getSchemaMetadata($schema, $type, $refresh)
    {
        return $this->getTableNames($schema, $refresh)->then(
            function($names) use($schema, $type, $refresh) {
                $metadata = [];
                $metaPromises = [];
                $methodName = 'getTable' . ucfirst($type);
                if (method_exists($this, $methodName . 'Async')) {
                    $methodName .= 'Async';
                }
                foreach ($names as $name) {
                    if ($schema !== '') {
                        $name = $schema . '.' . $name;
                    }
                    /** @var ExtendedPromiseInterface $metaPromise */
                    $metaPromise = $this->$methodName($name, $refresh);
                    $metaPromise->then(
                        function($tableMetadata) use(&$metadata) {
                            if ($tableMetadata !== null) {
                                $metadata[] = $tableMetadata;
                            }
                        },
                        function() { return null; }
                    );
                    $metaPromises[] = $metaPromise;
                }
                return all($metaPromises)
                    ->then(function() use (&$metadata) {
                        return $metadata;
                    });
            }
        );
    }


    /**
     * Sets the metadata of the given type for the given table.
     * @param string $name table name.
     * @param string $type metadata type.
     * @param mixed $data metadata.
     */
    protected function setTableMetadata($name, $type, $data)
    {
        $rawName = $this->getRawTableName($name);
        $this->_tableMetadata[$rawName] = isset($this->_tableMetadata[$rawName]) ? $this->_tableMetadata[$rawName] : [];
        $this->_tableMetadata[$rawName][$type] = $data;
    }

    /**
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return TableSchema|null DBMS-dependent table metadata, `null` if the table does not exist.
     * @throws NotSupportedException
     */
    protected function loadTableSchema($name)
    {
        throw new NotSupportedException("Loading table schema is not supported by this DB");
    }

    /**
     * Resolves the table name and schema name (if any).
     * @param TableSchema $table the table metadata object
     * @param string      $name the table name
     * @throws NotSupportedException
     */
    protected function resolveTableNames($table, $name)
    {
        throw new NotSupportedException(get_class($this) . ' does not support resolving all table names.');
    }

    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return ColumnSchema the column schema object
     * @throws NotSupportedException
     */
    protected function loadColumnSchema($info)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading column schema');
    }

    /**
     * Creates a column schema for the database.
     * This method may be overridden by child classes to create a DBMS-specific column schema.
     * @return ColumnSchemaInterface column schema instance.
     */
    protected function createColumnSchema()
    {
        return $this->db->createColumnSchema();
    }

    /**
     * Extracts the PHP type from abstract DB type.
     * @param ColumnSchema $column the column schema information
     * @return string PHP type name
     */
    protected function getColumnPhpType($column)
    {
        static $typeMap = [
            // abstract type => php type
            self::TYPE_TINYINT => 'integer',
            self::TYPE_SMALLINT => 'integer',
            self::TYPE_INTEGER => 'integer',
            self::TYPE_BIGINT => 'integer',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_FLOAT => 'double',
            self::TYPE_DOUBLE => 'double',
            self::TYPE_BINARY => 'resource',
            self::TYPE_JSON => 'array',
        ];
        if (isset($typeMap[$column->type])) {
            if ($column->type === 'bigint') {
                return PHP_INT_SIZE === 8 && !$column->unsigned ? 'integer' : 'string';
            } elseif ($column->type === 'integer') {
                return PHP_INT_SIZE === 4 && $column->unsigned ? 'string' : 'integer';
            }

            return $typeMap[$column->type];
        }

        return 'string';
    }

    /**
     * Returns the cache key for the specified table name.
     * @param string $name the table name.
     * @return mixed the cache key.
     */
    protected function getCacheKey($name)
    {
        return [
            __CLASS__,
            $this->db->getDsn(),
            $this->db->username,
            $this->getRawTableName($name),
        ];
    }

    /**
     * Returns the cache tag name.
     * This allows [[refresh()]] to invalidate all cached table schemas.
     * @return string the cache tag name
     */
    protected function getCacheTag()
    {
        return md5(serialize([
            __CLASS__,
            $this->db->getDsn(),
            $this->db->username,
        ]));
    }

    /**
     * Get server version promised
     * @return ExtendedPromiseInterface
     */
    protected function getServerVersionPromised() {
        return resolve(null);
    }

    /**
     * Refresh table schemas in cache and $_tableSchemas
     * @param string $schema
     * @return ExtendedPromiseInterface
     */
    protected function refreshTablesMetadata($schema = '') {
        $schema = $schema !== '' ? $schema : $this->defaultSchema;
        $metaTypes = $this->tableMetaImplemented;
        return $this->getTableNames($schema, true)->then(
            function($names) use ($metaTypes) {
                $promises = [];
                $this->_tableMetadata = [];
                foreach ($names as $name) {
                    $promises[] = $this->refreshTableMetadata($name, true);
                }
                return !empty($promises) ? all($promises) : resolve(true);
            }
        );
    }

    /**
     * Init component
     * @return ExtendedPromiseInterface
     */
    public function initComponent()
    {
        $promises = [];
        $promises[] = $this->getServerVersionPromised()->otherwise(function() { return false; });
        $promises[] = $this->refreshTablesMetadata();
        return all($promises);
    }

    /**
     * Obtains the primary key for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool   $refresh whether to reload the information even if it is found in the cache.
     * @return Constraint|null table primary key, `null` if the table has no primary key.
     */
    public function getTablePrimaryKey($name, $refresh = false)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading primary key');
    }

    /**
     * Obtains the foreign keys information for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool   $refresh whether to reload the information even if it is found in the cache.
     * @return ForeignKeyConstraint[] table foreign keys.
     */
    public function getTableForeignKeys($name, $refresh = false)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading foreign keys');
    }

    /**
     * Obtains the indexes information for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool   $refresh whether to reload the information even if it is found in the cache.
     * @return IndexConstraint[] table indexes.
     */
    public function getTableIndexes($name, $refresh = false)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading indexes');
    }

    /**
     * Obtains the unique constraints information for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool   $refresh whether to reload the information even if it is found in the cache.
     * @return Constraint[] table unique constraints.
     */
    public function getTableUniques($name, $refresh = false)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading uniques');
    }

    /**
     * Obtains the check constraints information for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool   $refresh whether to reload the information even if it is found in the cache.
     * @return CheckConstraint[] table check constraints.
     */
    public function getTableChecks($name, $refresh = false)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading checks');
    }

    /**
     * Obtains the default value constraints information for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool   $refresh whether to reload the information even if it is found in the cache.
     * @return DefaultValueConstraint[] table default value constraints.
     */
    public function getTableDefaultValues($name, $refresh = false)
    {
        throw new NotSupportedException(get_class($this) . ' does not support loading default values');
    }
}