<?php

namespace Reaction\Db;
use Reaction\Cache\ExpiringCacheInterface;
use Reaction\Cache\ExtendedCacheInterface;

/**
 * Interface DatabaseInterface
 * @package Reaction\Db
 * @property string  $host
 * @property integer $port
 * @property string  $database
 * @property string  $username
 * @property string  $password
 * @property integer $schemaCacheDuration
 * @property bool    $schemaCacheEnable
 * @property array   $schemaCacheExclude
 */
interface DatabaseInterface
{
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
     * Get connection
     * @return ConnectionInterface
     */
    public function createConnection();

    /**
     * Create command
     * @param ConnectionInterface|null $connection
     * @return CommandInterface
     */
    public function createCommand($connection = null);

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
}