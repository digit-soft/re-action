<?php

namespace Reaction\Db\Pgsql;

use PgAsync\Client as pgClient;
use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\Deferred;
use Reaction\Promise\ExtendedPromiseInterface;

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
    ];

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
     * Get \PgAsync\Client instance
     * @return \PgAsync\Client
     */
    protected function getPgClient() {
        if (!isset($this->_pgClient)) {
            $params = [
                'host' => $this->host,
                'port' => $this->port,
                'user' => $this->username,
                'password' => $this->password,
                'database' => $this->database,
            ];
            $loop = \Reaction::$app->loop;
            $this->_pgClient = new pgClient($params, $loop);
        }
        return $this->_pgClient;
    }

    /**
     * Execute SQL statement string
     * @param string $sql SQL statement been executed
     * @param array  $params An array of statement parameters
     * @return ExtendedPromiseInterface
     */
    public function executeSql($sql, $params = []) {
        list($sql, $params) = $this->convertSqlToIndexed($sql, $params);
        $deferred = new Deferred();
        $result = [];
        $this->getPgClient()->executeStatement($sql, $params)->subscribe(
            function($row) use (&$result) {
                $result[] = $row;
            },
            function($error = null) use (&$deferred) {
                $deferred->reject($error);
            },
            function() use (&$deferred, &$result) {
                $deferred->resolve($result);
            }
        );

        return $deferred->promise();
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