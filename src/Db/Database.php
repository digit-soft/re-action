<?php

namespace Reaction\Db;

use Reaction\Base\Component;
use Reaction\Cache\ExpiringCacheInterface;
use Reaction\Cache\ExtendedCacheInterface;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ArrayHelper;

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

    /**
     * @var array Schema, QueryBuilder, Connection and Command classes config
     */
    public $componentsConfig = [
        'Reaction\Db\SchemaInterface' => 'Reaction\Db\Schema',
        'Reaction\Db\QueryBuilderInterface' => 'Reaction\Db\QueryBuilder',
        'Reaction\Db\CommandInterface' => 'Reaction\Db\Command',
        'Reaction\Db\ConnectionInterface' => 'Reaction\Db\Connection',
    ];

    /**
     * @var int Schema cache duration in seconds
     */
    public $schemaCacheDuration = 10;
    /**
     * @var bool Enable schema caching
     */
    public $schemaCacheEnable = false;
    /**
     * @var array Table names excluded from caching
     */
    public $schemaCacheExclude = [];

    public $cacheComponent;

    protected $_queryBuilder;
    protected $_schema;
    protected $_cache;

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
     * Get connection
     * @return ConnectionInterface
     */
    public function createConnection() {
        /** @var ConnectionInterface $connection */
        $connection = $this->createComponent(ConnectionInterface::class);
        return $connection;
    }

    /**
     * Create command
     * @param ConnectionInterface|null $connection
     * @return CommandInterface
     */
    public function createCommand($connection = null) {
        if (!isset($connection)) {
            $connection = $this->createConnection();
        }
        $config = [ 'connection' => $connection ];
        /** @var CommandInterface $command */
        $command = $this->createComponent(CommandInterface::class, $config, false);
        return $command;
    }

    /**
     * Get dsn string for cache keys
     * @return string
     */
    public function getDsn() {
        return "tcp://{$this->username}:{$this->password}@{$this->host}:{$this->port}/" . $this->database;
    }

    /**
     * Get DB connection credentials
     * @return array
     */
    public function getCredentials() {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
        ];
    }

    /**
     * Create inner component
     * @param string $interface
     * @param array  $config
     * @param bool   $injectDb
     * @return QueryBuilderInterface|SchemaInterface|CommandInterface|ConnectionInterface
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
            $config['db'] = $this;
        }
        return \Reaction::create($_config);
    }
}