<?php

namespace Reaction\Web\Sessions;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Reaction\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use Reaction\Base\Component;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\SessionException;
use Reaction\Helpers\Json;
use Reaction\RequestApplicationInterface;
use function Reaction\Promise\resolve;

/**
 * Class SessionHandlerAbstract
 * @package Reaction\Web\Sessions
 */
abstract class SessionHandlerAbstract extends Component implements SessionHandlerInterface
{
    /**
     * @var LoopInterface Event loop instance
     */
    public $loop;
    /**
     * @var integer Session lifetime in seconds for garbage collector.
     * After that time GC will remove data from storage and archive it for '$sessionLifetime' seconds
     * @see $gcArchiveLifetime
     */
    public $gcLifetime = 3;
    /**
     * @var integer Session lifetime in seconds (default 7 days).
     * Time for archive session life from where it can be restored
     */
    public $gcArchiveLifetime = 3600;
    /**
     * @var integer GC timer interval in seconds
     */
    public $timerInterval = 3;
    /**
     * @var bool Whenever do not use internal garbage collector
     */
    public $useExternalGc = false;
    /**
     * @var SessionArchiveInterface|array|string
     */
    public $archive;

    /** @var TimerInterface Timer for GC */
    protected $_timer;
    /** @var bool Flag that garbage collector not finished his work */
    protected $_gcIsRunning = false;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     * @throws SessionException
     */
    public function init()
    {
        if (is_string($this->archive) || is_array($this->archive)) {
            $this->archive = \Reaction::create($this->archive);
        } elseif (!$this->archive instanceof SessionArchiveInterface) {
            $message = sprintf("Property `archive` must be set with instance of `%s` or configuration array|string", SessionArchiveInterface::class);
            throw new SessionException($message);
        }
        if (!$this->useExternalGc) {
            $this->createGcTimer();
        }
        parent::init();
    }

    /**
     * Read session data and returns serialized|encoded data
     * @param string $id
     * @return ExtendedPromiseInterface  with session data
     */
    abstract public function read($id);

    /**
     * Write session data to storage
     * @param string $id
     * @param array  $data
     * @return ExtendedPromiseInterface  with session data
     */
    abstract public function write($id, $data);

    /**
     * Destroy a session
     * @param string $id The session ID being destroyed.
     * @return ExtendedPromiseInterface  with bool when finished
     */
    abstract public function destroy($id);

    /**
     * Regenerate session id
     * @param string                      $idOld
     * @param RequestApplicationInterface $app
     * @param bool                        $deleteOldSession
     * @return ExtendedPromiseInterface  with new session ID (string)
     */
    public function regenerateId($idOld, RequestApplicationInterface $app, $deleteOldSession = false)
    {
        $self = $this;
        $dataOld = [];
        $promise = $this->read($idOld)->then(
            function($data) { return $data; },
            function() { return []; }
        )->then(function($data) use ($idOld, &$dataOld) {
            $dataOld = $data;
            return $this->destroy($idOld)->then(null, function() { return true; });
        })->then(
            function() use ($self, &$app) {
                return $self->createId($app);
            }
        )->then(
            function($newId) use ($self, &$dataOld, $deleteOldSession) {
                $retCallback = function() use ($newId) { return $newId; };
                $dataNew = $deleteOldSession ? [] : $dataOld;
                return $self->write($newId, $dataNew)->then($retCallback, $retCallback);
            },
            function() use ($idOld) {
                $message = sprintf('Failed to regenerate session ID for session "%s"', $idOld);
                throw new SessionException($message);
            }
        );
        return $promise;
    }

    /**
     * Cleanup old sessions. Timer callback.
     * Sessions that have not updated for the last '$this->gcLifetime' seconds will be archived.
     * Sessions in archive with age bigger than '$this->sessionLifetime' seconds will be removed.
     * @see $gcLifetime
     * @see $gcArchiveLifetime
     * @return void
     */
    abstract public function gc();

    /**
     * Return a new session ID
     * @param RequestApplicationInterface $app
     * @return ExtendedPromiseInterface  with session ID valid for session handler
     */
    public function createId(RequestApplicationInterface $app)
    {
        $ip = $app->reqHelper->getRemoteIP();
        if (empty($ip)) {
            $ip = '127.0.0.1';
        }
        $time = microtime();
        try {
            $rand = \Reaction::$app->security->generateRandomString(16);
        } catch (\Exception $exception) {
            $rand = mt_rand(11111, 99999);
        }
        $id = md5($ip . ':' . $time . ':' . $rand);
        $self = $this;
        return $this->checkSessionId($id)->then(
            function() use($id) { return $id; },
            function() use ($self, &$app) { return $self->createId($app); }
        );
    }

    /**
     * Update timestamp of a session
     * @param string $id The session id
     * @param string $data
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * @return ExtendedPromiseInterface  with bool when finished
     */
    abstract public function updateTimestamp($id, $data);

    /**
     * Check session id for uniqueness
     * @param string $sessionId
     * @return ExtendedPromiseInterface
     */
    public function checkSessionId($sessionId)
    {
        return resolve(true);
    }

    /**
     * Serialize session data to string
     * @param array $data
     * @return string
     */
    public function serializeData($data)
    {
        return Json::encode($data);
    }

    /**
     * Unserialize session data from string
     * @param string $dataSerialized
     * @return array
     */
    public function unserializeData($dataSerialized)
    {
        return Json::decode($dataSerialized);
    }

    /**
     * Create GC periodic timer
     */
    protected function createGcTimer()
    {
        if (isset($this->_timer) && $this->_timer instanceof TimerInterface) {
            $this->loop->cancelTimer($this->_timer);
        }
        $this->_timer = $this->loop->addPeriodicTimer($this->timerInterval, [$this, 'gc']);
    }
}