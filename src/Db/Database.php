<?php

namespace Reaction\Db;

use Reaction\Base\Component;
use Reaction\Cache\ExpiringCacheInterface;
use Reaction\Cache\ExtendedCacheInterface;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\all;
use function Reaction\Promise\resolve;

/**
 * Class Database
 * @package Reaction\Db
 */
class Database extends Component implements DatabaseInterface
{
    public $host = 'localhost';
    public $port = '5432';
    public $username;
    public $password;
    public $database;

    public $tablePrefix = '';

    /**
     * @var array Schema, QueryBuilder, Connection and Command classes config
     */
    public $componentsConfig = [];

    /**
     * @var int Schema cache duration in seconds
     */
    public $schemaCacheDuration = 3600;
    /**
     * @var bool Enable schema caching
     */
    public $schemaCacheEnable = true;
    /**
     * @var array Table names excluded from caching
     */
    public $schemaCacheExclude = [];
    /**
     * @var int Query cache duration
     */
    public $queryCacheDuration = 60;
    /**
     * @var string Cache component name
     */
    public $cacheComponent = 'arrayCacheDefault';

    protected $_queryBuilder;
    protected $_schema;
    protected $_cache;
    protected $_initialized = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->componentsConfig = ArrayHelper::merge($this->getDefaultComponentsConfig(), $this->componentsConfig);
        parent::init();
    }

    /**
     * Get DB driver name
     * @return string
     */
    public function getDriverName() {
        return 'unknown';
    }

    /**
     * Get cache component
     * @return ExpiringCacheInterface|null
     */
    public function getCache() {
        if (!isset($this->_cache) && isset($this->cacheComponent)) {
            if ($this->cacheComponent instanceof ExtendedCacheInterface) {
                $this->_cache = $this->cacheComponent;
            } else {
                $this->_cache = \Reaction::create($this->cacheComponent);
            }
        }
        return $this->_cache;
    }

    /**
     * Get query builder
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder() {
        if (!isset($this->_queryBuilder)) {
            /** @var QueryBuilderInterface $queryBuilder */
            $queryBuilder = $this->createComponent(QueryBuilderInterface::class);
            $this->_queryBuilder = $queryBuilder;
        }
        return $this->_queryBuilder;
    }

    /**
     * Get Schema
     * @return SchemaInterface
     */
    public function getSchema() {
        if (!isset($this->_schema)) {
            /** @var SchemaInterface $schema */
            $schema = $this->createComponent(SchemaInterface::class);
            $this->_schema = $schema;
        }
        return $this->_schema;
    }

    /**
     * Create command
     * @param string|null $sql
     * @param array       $params
     * @return CommandInterface
     */
    public function createCommand($sql = null, $params = []) {
        /** @var CommandInterface $command */
        $config = ['sql' => $sql, 'params' => $params];
        $command = $this->createComponent(CommandInterface::class, $config);
        return $command;
    }

    /**
     * Create ColumnSchemaInterface instance
     * @return ColumnSchemaInterface
     */
    public function createColumnSchema() {
        $colSchema = $this->createComponent(ColumnSchemaInterface::class, [], false);
        return $colSchema;
    }

    /**
     * Get dsn string for cache keys
     * @return string
     */
    public function getDsn() {
        return "tcp://{$this->username}:{$this->password}@{$this->host}:{$this->port}/" . $this->database;
    }

    /**
     * Create inner component
     * @param string $interface
     * @param array  $config
     * @param bool   $injectDb
     * @return QueryBuilderInterface|SchemaInterface|CommandInterface|ColumnSchemaInterface
     * @throws null
     */
    protected function createComponent($interface, $config = [], $injectDb = true) {
        if (!isset($this->componentsConfig[$interface])) {
            $message = sprintf('Suitable component for interface "%s" not configured in "%s"', $interface, __CLASS__);
            throw new InvalidConfigException($message);
        }
        $className = $this->componentsConfig[$interface];
        $_config = is_array($className) ? $className : [ 'class' => $className ];
        $_config = ArrayHelper::merge($_config, $config);
        if ($injectDb) {
            $_config = ArrayHelper::merge([ 'db' => $this ], $_config);
        }
        return \Reaction::create($_config);
    }

    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $value string to be quoted
     * @return string the properly quoted string
     * @see http://php.net/manual/en/pdo.quote.php
     */
    public function quoteValue($value)
    {
        return $this->getSchema()->quoteValue($value);
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName($name)
    {
        return $this->getSchema()->quoteTableName($name);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name)
    {
        return $this->getSchema()->quoteColumnName($name);
    }

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     * Tokens enclosed within double curly brackets are treated as table names, while
     * tokens enclosed within double square brackets are column names. They will be quoted accordingly.
     * Also, the percentage character "%" at the beginning or ending of a table name will be replaced
     * with [[tablePrefix]].
     * @param string $sql the SQL to be quoted
     * @return string the quoted SQL
     */
    public function quoteSql($sql)
    {
        return preg_replace_callback(
            '/(\\{\\{(%?[\w\-\. ]+%?)\\}\\}|\\[\\[([\w\-\. ]+)\\]\\])/',
            function ($matches) {
                if (isset($matches[3])) {
                    return $this->quoteColumnName($matches[3]);
                }

                return str_replace('%', $this->tablePrefix, $this->quoteTableName($matches[2]));
            },
            $sql
        );
    }

    /**
     * Execute SQL statement string
     * @param string $sql
     * @param array  $params
     * @throws NotSupportedException
     * @return ExtendedPromiseInterface
     */
    public function executeSql($sql, $params = []) {
        throw new NotSupportedException("Query execution is not supported by this driver");
    }

    /**
     * Get default components config
     * @return array
     */
    protected function getDefaultComponentsConfig() {
        return [
            'Reaction\Db\SchemaInterface' => 'Reaction\Db\Schema',
            'Reaction\Db\QueryBuilderInterface' => 'Reaction\Db\QueryBuilder',
            'Reaction\Db\CommandInterface' => 'Reaction\Db\Command',
            'Reaction\Db\ColumnSchemaInterface' => 'Reaction\Db\ColumnSchema',
        ];
    }

    /**
     * Init callback. Called by parent container/service/component on init and must return a fulfilled Promise
     * @return ExtendedPromiseInterface
     */
    public function initComponent()
    {
        $promises = [];
        $schema = $this->getSchema();
        $promises[] = $schema->initComponent();
        return all($promises)->always(function() {
            \Reaction::info('{class} initialized', ['class' => get_called_class()]);
            return true;
        });
    }

    /**
     * Check that component was initialized earlier
     * @return bool
     */
    public function isInitialized()
    {
        return $this->_initialized;
    }
}