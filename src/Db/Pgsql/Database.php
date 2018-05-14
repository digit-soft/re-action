<?php

namespace Reaction\Db\Pgsql;

/**
 * Class Database
 * @package Reaction\Db\Pgsql
 */
class Database extends \Reaction\Db\Database
{
    /**
     * @var array Schema, QueryBuilder, Connection and Command classes config
     */
    public $componentsConfig = [
        'Reaction\Db\SchemaInterface' => 'Reaction\Db\Pgsql\Schema',
        'Reaction\Db\QueryBuilderInterface' => 'Reaction\Db\Pgsql\QueryBuilder',
        'Reaction\Db\CommandInterface' => 'Reaction\Db\Command',
        'Reaction\Db\ConnectionInterface' => 'Reaction\Db\Connection',
    ];

    protected $_pgClient;

    protected function getPgClient() {
        if (!isset($this->_pgClient)) {

        }
        return $this->_pgClient;
    }
}