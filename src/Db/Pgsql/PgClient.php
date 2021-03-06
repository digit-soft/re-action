<?php

namespace Reaction\Db\Pgsql;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use Reaction\Base\BaseObject;
use Reaction\ClientsPool\Pool;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Helpers\ArrayHelper;
use Rx\Observable;
use Reaction\Db\Pgsql\PgConnection as pgConnection;

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
    /**
     * @var array Pool config
     * @see Pool
     */
    public $poolConfig = [];
    /**
     * @var array Default Pool config
     */
    public $poolConfigDefault = [
        'clientTtl' => 40,      //Max connection time-to-live
        'maxCount' => 90,       //Max simultaneously opened connections count in pool
        'maxQueueCount' => 10,  //Max queue count
    ];

    /** @var pgConnection[] Connections pool */
    protected $connections = [];
    /**
     * @var array Parameters for connection creation
     */
    protected $connectionParams = [
        'autoDisconnect' => false,
    ];
    /**
     * @var Pool|null
     */
    protected $_pool;

    /**
     * @var int Max simultaneously opened connections count in pool
     * @deprecated Not used at this time
     * @todo Remove later
     */
    private $maxConnections = 90;

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
        $this->connectionParams['autoDisconnect'] = !empty($this->autoDisconnect);

        parent::init();
    }

    /**
     * Get pool of connections
     * @return Pool|null
     */
    public function getPool()
    {
        if (!isset($this->_pool)) {
            $poolConfig = [
                'loop' => $this->loop,
                'clientConfig' => [
                    ['class' => pgConnection::class],
                    [
                        $this->connectionParams,
                        $this->loop,
                        $this->connector
                    ]
                ]
            ];
            $poolConfig = ArrayHelper::merge($this->poolConfigDefault, $this->poolConfig, $poolConfig);
            $this->_pool = new Pool($poolConfig);
        }
        return $this->_pool;
    }

    /**
     * Execute simple statement without parameters
     * @param string $s
     * @return Observable
     */
    public function query($s)
    {
        return Observable::defer(function() use ($s) {
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
        return Observable::defer(function() use ($queryString, $parameters) {
            $conn = $this->getLeastBusyConnection();

            return $conn->executeStatement($queryString, $parameters);
        });
    }

    /**
     * Get least busy connection, with idle state or at least with minimum queued queries count
     * @return pgConnection
     */
    private function getLeastBusyConnection() : pgConnection
    {
        /** @var pgConnection $client */
        $client = $this->getPool()->getClient();
        return $client;
    }

    /**
     * Get connection ready for query execution
     * @return pgConnection|null
     */
    public function getIdleConnection(): pgConnection
    {
        if(($connection = $this->getPool()->getClientIdle()) !== null) {
            return $connection;
        } elseif (!$this->getPool()->isReachedMaxClients()) {
            return $this->getPool()->createClient();
        }
        return null;
    }

    /**
     * Create new connection without adding it to shared pool
     * @return pgConnection
     */
    public function getDedicatedConnection() {
        return $this->getPool()->createClient(false);
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

    /**
     * Create new connection
     * @param bool  $addToPool
     * @param array $params
     * @return pgConnection
     * @deprecated Since Pool is used
     * @see getPool()
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

        $connection->on('close', function() use ($connection) {
            $this->connections = array_filter($this->connections, function($c) use ($connection) {
                return $connection !== $c;
            });
            $this->connections = array_values($this->connections);
        });

        return $connection;
    }

    /**
     * Get connection ready for query execution
     * @return pgConnection
     * @deprecated
     * @see getIdleConnection()
     */
    private function getIdleConnectionOld(): pgConnection
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
     * Get least busy connection, with idle state or at least with minimum queued queries count
     * @return pgConnection
     * @throws Exception
     * @deprecated
     * @see getLeastBusyConnection()
     */
    private function getLeastBusyConnectionOld() : pgConnection
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
}