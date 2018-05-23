<?php
/**
 * Created by PhpStorm.
 * User: digit
 * Date: 22.05.18
 * Time: 15:21
 */

namespace Reaction\Db\Pgsql;


use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromise;
use Reaction\Promise\LazyPromiseInterface;
use Reaction\Promise\Promise;

class Connection extends \Reaction\Db\Connection
{
    /** @var \PgAsync\Connection */
    public $pgConnection;
    /**
     * Execute SQL statement string
     * @param string $sql Statement SQL string
     * @param array  $params Statement parameters
     * @param bool   $lazy Use lazy promise
     * @return ExtendedPromiseInterface|LazyPromiseInterface
     */
    public function executeSql($sql, $params = [], $lazy = true)
    {
        list($sql, $params) = $this->convertSqlToIndexed($sql, $params);
        $promiseResolver = function($r, $c) use ($sql, $params) {
            $this->pgConnection->executeStatement($sql, $params)->subscribe(
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
     * Close connection
     */
    public function close()
    {
        $this->pgConnection->disconnect();
        parent::close();
    }

    /**
     * Convert keyed SQL and params to indexed by `$`
     * @param string $sql
     * @param array  $params
     * @return array
     */
    protected function convertSqlToIndexed($sql, $params = [])
    {
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