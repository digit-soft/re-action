<?php

namespace Reaction\Db\Pgsql;

use Reaction;
use Reaction\Db\ConnectionInterface;
use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromise;
use Reaction\Promise\LazyPromiseInterface;
use Reaction\Promise\Promise;

/**
 * Class Database
 * @package Reaction\Db\Pgsql
 * @property pgClient $pgClient
 */
class Database extends \Reaction\Db\Database
{
    /**
     * @var array Schema, QueryBuilder, ColumnSchema and Command classes config
     */
    public $componentsConfig = [
        'Reaction\Db\SchemaInterface' => 'Reaction\Db\Pgsql\Schema',
        'Reaction\Db\QueryBuilderInterface' => 'Reaction\Db\Pgsql\QueryBuilder',
        'Reaction\Db\ColumnSchemaInterface' => 'Reaction\Db\Pgsql\ColumnSchema',
        'Reaction\Db\ConnectionInterface' => 'Reaction\Db\Pgsql\Connection',
    ];

    /**
     * @var bool Enable savepoint support
     */
    public $enableSavepoint = true;

    /** @var \PgAsync\Client */
    protected $_pgClient;

    /**
     * Get DB driver name
     * @return string
     */
    public function getDriverName() {
        return 'pgsql';
    }

    /**
     * Get PgClient instance
     * @return PgClient
     */
    protected function getPgClient() {
        if (!isset($this->_pgClient)) {
            $config = [
                'dbCredentials' => [
                    'host' => $this->host,
                    'port' => $this->port,
                    'user' => $this->username,
                    'password' => $this->password,
                    'database' => $this->database,
                ],
                'loop' => Reaction::$app->loop
            ];
            $this->_pgClient = new PgClient($config);
        }
        return $this->_pgClient;
    }

    /**
     * Execute SQL statement string
     * @param string $sql Statement SQL string
     * @param array  $params Statement parameters
     * @param bool   $lazy Use lazy promise
     * @return ExtendedPromiseInterface|LazyPromiseInterface
     */
    public function executeSql($sql, $params = [], $lazy = true) {
        list($sql, $params) = $this->convertSqlToIndexed($sql, $params);
        $promiseResolver = function($r, $c) use ($sql, $params) {
            $this->getPgClient()->executeStatement($sql, $params)->subscribe(
                function($row) use (&$result) {
                    $result[] = $row;
                },
                function($error = null) use (&$c) {
                    $c($error);
                },
                function() use (&$r, &$result) {
                    $r($result);
                }
            );
        };
        if (!$lazy) {
            return new Promise($promiseResolver);
        }
        $promiseCreator = function() use (&$promiseResolver) {
            return new Promise($promiseResolver);
        };
        return new LazyPromise($promiseCreator);
    }

    /**
     * Get dedicated connection (Not used in shared pool)
     * @return ConnectionInterface
     */
    public function getDedicatedConnection() {
        $config = [
            'pgConnection' => $this->getPgClient()->getDedicatedConnection(false),
        ];
        return $this->createComponent(ConnectionInterface::class, $config);
    }

    /**
     * Convert keyed SQL and params to indexed by `$`
     * @param string $sql
     * @param array  $params
     * @return array
     */
    protected function convertSqlToIndexed($sql, $params = []) {
        if (empty($params) || ArrayHelper::isIndexed($params)) {
            return [$sql, $params];
        }
        $newParams = array_values($params);
        $replace = [];
        $num = 1;
        foreach ($params as $key => $value) {
            $replace[$key] = '$' . $num;
            $num++;
        }
        $newSql = strtr($sql, $replace);
        return [$newSql, $newParams];
    }
}