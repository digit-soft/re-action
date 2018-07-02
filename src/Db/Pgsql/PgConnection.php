<?php

namespace Reaction\Db\Pgsql;

use Evenement\EventEmitter;
use PgAsync\Column;
use PgAsync\Command\Bind;
use PgAsync\Command\CancelRequest;
use PgAsync\Command\Close;
use PgAsync\Command\Describe;
use PgAsync\Command\Execute;
use PgAsync\Command\Parse;
use PgAsync\Command\PasswordMessage;
use PgAsync\Command\Sync;
use PgAsync\Command\Terminate;
use PgAsync\Message\Authentication;
use PgAsync\Message\BackendKeyData;
use PgAsync\Message\CommandComplete;
use PgAsync\Command\CommandInterface;
use PgAsync\Message\CopyInResponse;
use PgAsync\Message\CopyOutResponse;
use PgAsync\Message\DataRow;
use PgAsync\Message\EmptyQueryResponse;
use PgAsync\Message\ErrorResponse;
use PgAsync\Message\Message;
use PgAsync\Message\NoticeResponse;
use PgAsync\Message\ParameterStatus;
use PgAsync\Message\ParseComplete;
use PgAsync\Command\Query;
use PgAsync\Message\ReadyForQuery;
use PgAsync\Message\RowDescription;
use PgAsync\Command\StartupMessage;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Stream\DuplexStreamInterface;
use Reaction\ClientsPool\ClientInterface;
use Reaction\ClientsPool\PoolClientTrait;
use Reaction\Helpers\ArrayHelper;
use Rx\Disposable\CallbackDisposable;
use Rx\Disposable\EmptyDisposable;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\ObserverInterface;
use Rx\SchedulerInterface;

class PgConnection extends EventEmitter implements ClientInterface
{
    use PoolClientTrait;

    // This is copied a lot of these states from the libpq library
    // Not many of these constants are used right now
    const STATE_IDLE = 0;
    const STATE_BUSY = 1;
    const STATE_READY = 2;
    const STATE_COPY_IN = 3;
    const STATE_COPY_OUT = 4;
    const STATE_COPY_BOTH = 5;

    const QUERY_SIMPLE = 0;
    const QUERY_EXTENDED = 1;
    const QUERY_PREPARE = 2;
    const QUERY_DESCRIBE = 3;

    const CONNECTION_OK = 0;
    const CONNECTION_BAD = 1;
    const CONNECTION_STARTED = 2;           /* Waiting for connection to be made.  */
    const CONNECTION_MADE = 3;              /* Connection OK; waiting to send.     */
    const CONNECTION_AWAITING_RESPONSE = 4; /* Waiting for a response from the
                                         * postmaster.        */
    const CONNECTION_AUTH_OK = 5;           /* Received authentication; waiting for
                                         * backend startup. */
    const CONNECTION_SETENV = 6;            /* Negotiating environment. */
    const CONNECTION_SSL_STARTUP = 7;       /* Negotiating SSL. */
    const CONNECTION_NEEDED = 8;            /* Internal state: connect() needed */
    const CONNECTION_CLOSED = 9;

    private $queryState;
    private $queryType;
    private $connStatus;

    /** @var DuplexStreamInterface */
    private $stream;

    /** @var ConnectorInterface */
    private $socket;

    private $parameters;

    /** @var LoopInterface */
    private $loop;

    /** @var CommandInterface[] */
    private $commandQueue;

    /** @var Message */
    private $currentMessage;

    /** @var CommandInterface */
    private $currentCommand;

    /** @var Column[] */
    private $columns = [];

    /** @var array */
    private $columnNames = [];

    /** @var string */
    private $lastError;

    /** @var BackendKeyData */
    private $backendKeyData;

    /** @var string */
    private $uri;

    /**
     * Can be 'I' for Idle, 'T' if in transactions block
     * or 'E' if in failed transaction block (queries will fail until end of trans)
     *
     * @var string
     */
    private $backendTransactionStatus = 'UNKNOWN';

    /** @var  bool */
    private $autoDisconnect = false;
    private $password;

    public function __construct(array $parameters, LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if (!isset($parameters['user']) || !isset($parameters['database'])) {
            throw new \InvalidArgumentException("Parameters must be an associative array with at least 'database' and 'user' set.");
        }

        if (!isset($parameters['host'])) {
            $parameters['host'] = '127.0.0.1';
        }

        if (!isset($parameters['port'])) {
            $parameters['port'] = '5432';
        }

        $this->password = ArrayHelper::remove($parameters, 'password', null);
        $this->autoDisconnect = ArrayHelper::remove($parameters, 'autoDisconnect', false);

        $this->parameters   = $parameters;
        $this->loop         = $loop;
        $this->commandQueue = [];
        $this->queryType    = static::QUERY_SIMPLE;
        $this->setState(static::STATE_BUSY);
        $this->setConnStatus(static::CONNECTION_NEEDED);
        $this->socket       = $connector ?: new Connector($loop);
        $this->uri          = 'tcp://' . $this->parameters['host'] . ':' . $this->parameters['port'];
    }

    /**
     * Start connection
     * @throws \Exception
     */
    private function start()
    {
        if ($this->connStatus !== static::CONNECTION_NEEDED) {
            throw new \Exception('Connection not in startable state');
        }

        $this->setConnStatus(static::CONNECTION_STARTED);

        $this->socket->connect($this->uri)->then(
            function (DuplexStreamInterface $stream) {
                $this->stream     = $stream;
                $this->setConnStatus(static::CONNECTION_MADE);

                $stream->on('close', [$this, 'onClose']);

                $stream->on('data', [$this, 'onData']);

                //  $ssl = new SSLRequest();
                //  $stream->write($ssl->encodedMessage());

                $startupParameters = $this->parameters;
                unset($startupParameters['host'], $startupParameters['port']);

                $startup = new StartupMessage();
                $startup->setParameters($startupParameters);
                $stream->write($startup->encodedMessage());
            },
            function ($e) {
                // connection error
                $this->failAllCommandsWith($e);
                $this->setConnStatus(static::CONNECTION_BAD);
                $this->emit('error', [$e]);
            }
        );
    }

    public function getState()
    {
        return $this->queryState;
    }

    /**
     * Set query state
     * @param int $state
     */
    protected function setState($state)
    {
        $this->queryState = $state;
        $map = [
            static::STATE_BUSY => static::CLIENT_POOL_STATE_BUSY,
            static::STATE_READY => static::CLIENT_POOL_STATE_READY,
        ];
        $statePool = isset($map[$state]) ? $map[$state] : static::CLIENT_POOL_STATE_NOT_READY;
        $this->changeState($statePool);
    }

    /**
     * Get Query commands queue length
     * @return int
     */
    public function getBacklogLength() : int
    {
        return array_reduce(
            $this->commandQueue,
            function ($a, CommandInterface $command) {
                if ($command instanceof Query || $command instanceof Sync) {
                    $a++;
                }
                return $a;
            },
            0);
    }

    /**
     * Handler for `data` event
     * @param $data
     */
    public function onData($data)
    {
        while (strlen($data) > 0) {
            $data = $this->processData($data);
        }
    }

    /**
     * Data processing callback
     * @param mixed $data
     * @return bool|string
     */
    private function processData($data)
    {
        if ($this->currentMessage) {
            $overflow = $this->currentMessage->parseData($data);
            // json_encode can slow things down here
            //$this->debug("onData: " . json_encode($overflow) . "");
            if ($overflow === false) {
                // there was not enough data to complete the message
                // leave this as the currentParser
                return '';
            }

            $this->handleMessage($this->currentMessage);

            $this->currentMessage = null;

            return $overflow;
        }

        if (strlen($data) == 0) {
            return '';
        }

        $type = $data[0];

        $message = Message::createMessageFromIdentifier($type);
        if ($message !== false) {
            $this->currentMessage = $message;
            return $data;
        }

//        if (in_array($type, ['R', 'S', 'D', 'K', '2', '3', 'C', 'd', 'c', 'G', 'H', 'W', 'D', 'I', 'E', 'V', 'n', 'N', 'A', 't', '1', 's', 'Z', 'T'])) {
//            $this->currentParser = [$this, 'parse1PlusLenMessage'];
//            call_user_func($this->currentParser, $data);
//        } else {
//            echo "Unhandled message \"".$type."\"";
//        }
    }

    /**
     * Handler for `close` event
     */
    public function onClose()
    {
        $this->setConnStatus(static::CONNECTION_CLOSED);
        $this->emit('close');
        $this->clientClose();
    }

    /**
     * Get connection status
     * @return int
     */
    public function getConnectionStatus()
    {
        return $this->connStatus;
    }

    /**
     * Set connection status
     * @param int $status
     */
    protected function setConnStatus($status)
    {
        $this->connStatus = $status;
        $map = [
            static::CONNECTION_CLOSED => static::CLIENT_POOL_STATE_CLOSING,
            static::CONNECTION_OK => static::CLIENT_POOL_STATE_READY,
        ];
        $state = isset($map[$status]) ? $map[$status] : static::CLIENT_POOL_STATE_NOT_READY;
        $this->changeState($state);
    }

    public function handleMessage($message)
    {
        $this->debug('Handling ' . get_class($message));
        if ($message instanceof DataRow) {
            $this->handleDataRow($message);
        } elseif ($message instanceof Authentication) {
            $this->handleAuthentication($message);
        } elseif ($message instanceof BackendKeyData) {
            $this->handleBackendKeyData($message);
        } elseif ($message instanceof CommandComplete) {
            $this->handleCommandComplete($message);
        } elseif ($message instanceof CopyInResponse) {
            $this->handleCopyInResponse($message);
        } elseif ($message instanceof CopyOutResponse) {
            $this->handleCopyOutResponse($message);
        } elseif ($message instanceof EmptyQueryResponse) {
            $this->handleEmptyQueryResponse($message);
        } elseif ($message instanceof ErrorResponse) {
            $this->handleErrorResponse($message);
        } elseif ($message instanceof NoticeResponse) {
            $this->handleNoticeResponse($message);
        } elseif ($message instanceof ParameterStatus) {
            $this->handleParameterStatus($message);
        } elseif ($message instanceof ParseComplete) {
            $this->handleParseComplete($message);
        } elseif ($message instanceof ReadyForQuery) {
            $this->handleReadyForQuery($message);
        } elseif ($message instanceof RowDescription) {
            $this->handleRowDescription($message);
        }
    }

    /**
     * @param DataRow $dataRow
     * @throws \Exception
     */
    private function handleDataRow(DataRow $dataRow)
    {
        if ($this->queryState === $this::STATE_BUSY && $this->currentCommand instanceof CommandInterface) {
            if (count($dataRow->getColumnValues()) !== count($this->columnNames)) {
                throw new \Exception('Expected ' . count($this->columnNames) . ' data values got ' . count($dataRow->getColumnValues()));
            }
            $row = array_combine($this->columnNames, $dataRow->getColumnValues());

            // this should be broken out into a "data-mapper" type thing
            // where objects can be added to allow formatting data as it is
            // processed according to the type
            foreach ($this->columns as $column) {
                if ($column->typeOid === 16) { // bool
                    if ($row[$column->name] === null) {
                        continue;
                    }
                    if ($row[$column->name] === 'f') {
                        $row[$column->name] = false;
                        continue;
                    }

                    $row[$column->name] = true;
                }
            }

            $this->currentCommand->next($row);
        }
    }

    /**
     * @param Authentication $message
     * @throws \Exception
     */
    private function handleAuthentication(Authentication $message)
    {
        $this->lastError = 'Unhandled authentication message: ' . $message->getAuthCode();
        if ($message->getAuthCode() === $message::AUTH_CLEARTEXT_PASSWORD ||
            $message->getAuthCode() === $message::AUTH_MD5_PASSWORD
        ) {
            if ($this->password === null) {
                $this->lastError = 'Server asked for password, but none was configured.';
            } else {
                $passwordToSend = $this->password;
                if ($message->getAuthCode() === $message::AUTH_MD5_PASSWORD) {
                    $salt           = $message->getSalt();
                    $passwordToSend = 'md5' .
                        md5(md5($this->password . $this->parameters['user']) . $salt);
                }
                $passwordMessage = new PasswordMessage($passwordToSend);
                $this->stream->write($passwordMessage->encodedMessage());

                return;
            }
        }
        if ($message->getAuthCode() === $message::AUTH_OK) {
            $this->setConnStatus(static::CONNECTION_AUTH_OK);

            return;
        }

        $this->setConnStatus(static::CONNECTION_BAD);
        $this->failAllCommandsWith(new \Exception($this->lastError));
        $this->emit('error', [new \Exception($this->lastError)]);
        $this->disconnect();
    }

    /**
     * @param BackendKeyData $message
     */
    private function handleBackendKeyData(BackendKeyData $message)
    {
        $this->backendKeyData = $message;
    }

    /**
     * @param CommandComplete $message
     */
    private function handleCommandComplete(CommandComplete $message)
    {
        if ($this->currentCommand instanceof CommandInterface) {
            $command = $this->currentCommand;
            $this->currentCommand = null;
            $command->complete();
        }
        $this->debug('Command complete.');
    }

    /**
     * @param CopyInResponse $message
     */
    private function handleCopyInResponse(CopyInResponse $message)
    {
    }

    /**
     * @param CopyOutResponse $message
     */
    private function handleCopyOutResponse(CopyOutResponse $message)
    {
    }

    /**
     * @param EmptyQueryResponse $message
     */
    private function handleEmptyQueryResponse(EmptyQueryResponse $message)
    {
    }

    /**
     * @param ErrorResponse $message
     * @throws \Exception
     */
    private function handleErrorResponse(ErrorResponse $message)
    {
        $this->lastError = $message;
        if ($message->getSeverity() === 'FATAL') {
            $this->setConnStatus(static::CONNECTION_BAD);
            // notify any waiting commands
            $this->processQueue();
        }
        if ($this->connStatus === $this::CONNECTION_MADE) {
            $this->setConnStatus(static::CONNECTION_BAD);
            // notify any waiting commands
            $this->processQueue();
        }
        if ($this->currentCommand !== null) {
            $extraInfo = null;
            if ($this->currentCommand instanceof Sync) {
                $extraInfo = [
                    'query_string' => $this->currentCommand->getDescription()
                ];
            } elseif ($this->currentCommand instanceof Query) {
                $extraInfo = [
                    'query_string' => $this->currentCommand->getQueryString()
                ];
            }
            $this->currentCommand->error(new ErrorException($message, $extraInfo));
            $this->currentCommand = null;
        }
    }

    /**
     * @param NoticeResponse $message
     */
    private function handleNoticeResponse(NoticeResponse $message)
    {
    }

    /**
     * @param ParameterStatus $message
     */
    private function handleParameterStatus(ParameterStatus $message)
    {
        $this->debug($message->getParameterName() . ': ' . $message->getParameterValue());
    }

    /**
     * @param ParseComplete $message
     */
    private function handleParseComplete(ParseComplete $message)
    {
    }

    /**
     * @param ReadyForQuery $message
     */
    private function handleReadyForQuery(ReadyForQuery $message)
    {
        $this->setConnStatus(static::CONNECTION_OK);
        $this->setState($this::STATE_READY);
        $this->currentCommand = null;
        $this->processQueue();
    }

    /**
     * @param RowDescription $message
     */
    private function handleRowDescription(RowDescription $message)
    {
        $this->addColumns($message->getColumns());
    }

    /**
     * @param \Throwable|null $e
     */
    private function failAllCommandsWith(\Throwable $e = null)
    {
        $e = $e ?: new \Exception('unknown error');

        while (count($this->commandQueue) > 0) {
            $c = array_shift($this->commandQueue);
            if ($c instanceof CommandInterface) {
                $c->error($e);
            }
        }
    }

    /**
     * Process current queue
     */
    public function processQueue()
    {
        if (count($this->commandQueue) === 0 && $this->queryState === static::STATE_READY && $this->autoDisconnect) {
            $this->commandQueue[] = new Terminate();
        }

        if (count($this->commandQueue) === 0) {
            return;
        }

        if ($this->connStatus === $this::CONNECTION_BAD) {
            $this->failAllCommandsWith(new \Exception('Bad connection: ' . $this->lastError));
            if ($this->stream) {
                $this->stream->end();
                $this->stream = null;
            }
            return;
        }

        while (count($this->commandQueue) > 0 && $this->queryState === static::STATE_READY) {
            /** @var CommandInterface $c */
            $c = array_shift($this->commandQueue);
            if (!$c->isActive()) {
                continue;
            }
            $this->debug('Sending ' . get_class($c));
            if ($c instanceof Query) {
                $this->debug('Sending simple query: ' . $c->getQueryString());
            }
            $this->stream->write($c->encodedMessage());
            if ($c instanceof Terminate) {
                $this->stream->end();
            }
            if ($c->shouldWaitForComplete()) {
                $this->setState($this::STATE_BUSY);
                if ($c instanceof Query) {
                    $this->queryType = $this::QUERY_SIMPLE;
                } elseif ($c instanceof Sync) {
                    $this->queryType = $this::QUERY_EXTENDED;
                }

                $this->currentCommand = $c;

                return;
            }
        }
    }

    /**
     * Execute simple query
     * @param string $query
     * @return Observable
     */
    public function query($query): Observable
    {
        return new AnonymousObservable(
            function (ObserverInterface $observer, SchedulerInterface $scheduler = null) use ($query) {
                if ($this->connStatus === $this::CONNECTION_NEEDED) {
                    $this->start();
                }
                if ($this->connStatus === $this::CONNECTION_BAD) {
                    $observer->onError(new \Exception('Connection failed'));
                    return new EmptyDisposable();
                }

                $q = new Query($query, $observer);
                $this->commandQueue[] = $q;
                $this->changeQueueCountInc();

                $this->processQueue();

                return new CallbackDisposable(function() use ($q) {
                    $this->changeQueueCountDec();
                    if ($this->currentCommand === $q && $q->isActive()) {
                        $this->cancelRequest();
                    }
                    $q->cancel();
                });
            }
        );

    }

    /**
     * Execute statement
     * @param string $queryString
     * @param array  $parameters
     * @return Observable
     */
    public function executeStatement(string $queryString, array $parameters = []): Observable
    {
        /**
         * http://git.postgresql.org/gitweb/?p=postgresql.git;a=blob;f=src/interfaces/libpq/fe-exec.c;h=828f18e1110119efc3bf99ecf16d98ce306458ea;hb=6bcce25801c3fcb219e0d92198889ec88c74e2ff#l1381
         *
         * Should make this return a Statement object
         *
         * To use prepared statements, looks like we need to:
         * - Parse (if needed?) (P)
         * - Bind (B)
         *   - Parameter Stuff
         * - Describe portal (D)
         * - Execute (E)
         * - Sync (S)
         *
         * Expect back
         * - Parse Complete (1)
         * - Bind Complete (2)
         * - Row Description (T)
         * - Row Data (D) 0..n
         * - Command Complete (C)
         * - Ready for Query (Z)
         */

        return new AnonymousObservable(
            function (ObserverInterface $observer, SchedulerInterface $scheduler = null) use ($queryString, $parameters) {
                if ($this->connStatus === $this::CONNECTION_NEEDED) {
                    $this->start();
                }
                if ($this->connStatus === $this::CONNECTION_BAD) {
                    $observer->onError(new \Exception('Connection failed'));
                    return new EmptyDisposable();
                }

                $name = 'somestatement';

                $close = new Close($name);
                $this->commandQueue[] = $close;

                $prepare = new Parse($name, $queryString);
                $this->commandQueue[] = $prepare;

                $bind = new Bind($parameters, $name);
                $this->commandQueue[] = $bind;

                $describe = new Describe();
                $this->commandQueue[] = $describe;

                $execute = new Execute();
                $this->commandQueue[] = $execute;

                $sync = new Sync($queryString, $observer);
                $this->commandQueue[] = $sync;

                $this->changeQueueCountInc();

                $this->processQueue();

                return new CallbackDisposable(function () use ($sync) {
                    $this->changeQueueCountDec();
                    if ($this->currentCommand === $sync && $sync->isActive()) {
                        $this->cancelRequest();
                    }
                    $sync->cancel();
                });
            }
        );
    }

    /**
     * Add Column information (from T)
     *
     * @param $columns
     */
    private function addColumns($columns)
    {
        $this->columns     = $columns;
        $this->columnNames = array_map(function ($column) {
            return $column->name;
        }, $this->columns);
    }

    /**
     * Print debug message
     * @param string $string
     */
    private function debug($string)
    {
        if (!\Reaction::isDebug()) {
            return;
        }
        //echo "DEBUG: " . $string . "\n";
    }

    /**
     * https://www.postgresql.org/docs/9.2/static/protocol-flow.html#AEN95792
     */
    public function disconnect()
    {
        $this->commandQueue[] = new Terminate();
        $this->processQueue();
    }

    /**
     * @inheritdoc
     */
    public function clientClose()
    {
        $this->disconnect();
        $this->emit(ClientInterface::CLIENT_POOL_EVENT_CLOSE);
    }

    /**
     * Cancel current request
     */
    private function cancelRequest()
    {
        if ($this->currentCommand !== null) {
            $this->socket->connect($this->uri)->then(function (DuplexStreamInterface $conn) {
                $cancelRequest = new CancelRequest($this->backendKeyData->getPid(), $this->backendKeyData->getKey());
                $conn->end($cancelRequest->encodedMessage());
            }, function (\Throwable $e) {
                $this->debug("Error connecting for cancellation... " . $e->getMessage() . "\n");
            });
        }
    }
}
