<?php

namespace Reaction\Db\Pgsql;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use Reaction\Base\BaseObject;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Helpers\ArrayHelper;
use Rx\Observable;
use PgAsync\Connection as pgConnection;

/**
 * Class PgClient
 * @package Reaction\Db\Pgsql
 */
class PgClient extends BaseObject
{
    /** @var LoopInterface */
    public $loop;
    /** @var ConnectorInterface */
    public $connector;
    /** @var int Max simultaneously opened connections count in pool */
    public $maxConnections = 5;
    /** @var bool Automatically close connection on idle */
    public $autoDisconnect = false;
    /**
     * @var array DB access credentials
     * [
     *  'host' => 'localhost',
     *  'port' => 5432,
     *  'user' => 'userName',
     *  'password' => 'userPassword',
     *  'database' => 'dbName',
     * ]
     */
    public $dbCredentials = [];

    /** @var pgConnection[] Connections pool */
    protected $connections = [];
    /**
     * @var array Parameters for connection creation
     */
    protected $connectionParams = [
        'auto_disconnect' => false,
    ];

    /**
     * PgClient constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->connectionParams = ArrayHelper::merge($this->connectionParams, $this->dbCredentials);
        $this->connectionParams['auto_disconnect'] = !empty($this->autoDisconnect);

        if (!is_int($this->maxConnections) || $this->maxConnections < 1) {
            throw new InvalidArgumentException('Property `maxConnections` must an be integer greater than zero.');
        }

        parent::init();
    }

    /**
     * Execute simple statement without parameters
     * @param string $s
     * @return Observable
     */
    public function query($s)
    {
        return Observable::defer(function () use ($s) {
            $conn = $this->getLeastBusyConnection();

            return $conn->query($s);
        });
    }

    /**
     * Execute statement with parameters
     * @param string $queryString
     * @param array  $parameters
     * @return Observable
     */
    public function executeStatement(string $queryString, array $parameters = [])
    {
        return Observable::defer(function () use ($queryString, $parameters) {
            $conn = $this->getLeastBusyConnection();

            return $conn->executeStatement($queryString, $parameters);
        });
    }

    /**
     * Get least busy connection, with idle state or at least with minimum queued queries count
     * @return pgConnection
     * @throws Exception
     */
    private function getLeastBusyConnection() : pgConnection
    {
        if (count($this->connections) === 0) {
            // try to spin up another connection to return
            $conn = $this->createNewConnection();
            if ($conn === null) {
                throw new Exception('There are no connections. Cannot find least busy one and could not create a new one.');
            }

            return $conn;
        }

        $min = reset($this->connections);

        foreach ($this->connections as $connection) {
            // if this connection is idle - just return it
            if ($connection->getBacklogLength() === 0 && $connection->getState() === pgConnection::STATE_READY) {
                return $connection;
            }

            if ($min->getBacklogLength() > $connection->getBacklogLength()) {
                $min = $connection;
            }
        }

        if (count($this->connections) < $this->maxConnections) {
            return $this->createNewConnection();
        }

        return $min;
    }

    /**
     * Get connection ready for query execution
     * @return pgConnection
     */
    public function getIdleConnection(): pgConnection
    {
        // we want to get the first available one
        // this will keep the connections at the front the busiest
        // and then we can add an idle timer to the connections
        foreach ($this->connections as $connection) {
            // need to figure out different states (in trans etc.)
            if ($connection->getState() === pgConnection::STATE_READY) {
                return $connection;
            }
        }

        if (count($this->connections) >= $this->maxConnections) {
            return null;
        }

        return $this->createNewConnection();
    }

    /**
     * Create new connection without adding it to shared pool
     * @param bool $autoDisconnect
     * @return pgConnection
     */
    public function getDedicatedConnection($autoDisconnect = false) {
        $params = [ 'auto_disconnect' => $autoDisconnect ];
        return $this->createNewConnection(false, $params);
    }

    /**
     * Create new connection
     * @param bool  $addToPool
     * @param array $params
     * @return pgConnection
     */
    private function createNewConnection($addToPool = true, $params = [])
    {
        // no idle connections were found - spin up new one
        $params = ArrayHelper::merge($this->connectionParams, $params);
        $connection = new pgConnection($params, $this->loop, $this->connector);
        if (!$addToPool || $this->autoDisconnect) {
            return $connection;
        }

        $this->connections[] = $connection;

        $connection->on('close', function () use ($connection) {
            $this->connections = array_filter($this->connections, function ($c) use ($connection) {
                return $connection !== $c;
            });
            $this->connections = array_values($this->connections);
        });

        return $connection;
    }

    /**
     * Get opened connections count
     * @return int
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * This is here temporarily so that the tests can disconnect
     * Will be setup better/more gracefully at some point hopefully
     *
     * @internal
     */
    public function closeNow()
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
    }
}