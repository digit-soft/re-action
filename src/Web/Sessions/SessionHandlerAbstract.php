<?php

namespace Reaction\Web\Sessions;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use Reaction\Base\Component;
use Reaction\Helpers\FileHelper;
use Reaction\Helpers\Json;
use Reaction\Web\AppRequestInterface;

class SessionHandlerAbstract extends Component implements SessionHandlerInterface
{
    /**
     * @var LoopInterface Event loop instance
     */
    public $loop;
    /**
     * @var integer Session lifetime in seconds for garbage collector.
     * After that time GC will remove data from storage and archive it for '$sessionLifetime' seconds
     * @see $sessionLifetime
     */
    public $gcLifetime = 8600;
    /**
     * @var integer Session lifetime in seconds (default 7 days).
     * Time for archive session life from where it can be restored
     */
    public $sessionLifetime = 604800;
    /**
     * @var integer GC timer interval in seconds
     */
    public $timerInterval = 5;
    /**
     * @var bool Whenever do not use internal garbage collector
     */
    public $useExternalGc = false;
    /**
     * @var string Session archive path
     */
    public $archivePath = '@runtime/session_archive';

    /** @var TimerInterface */
    protected $_timer;

    protected $_archivePath;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->useExternalGc) {
            $this->createGcTimer();
        }
        parent::init();
    }

    /**
     * Read session data and returns serialized|encoded data
     * @param string $sessionId
     * @return ExtendedPromiseInterface  with session data
     */
    public function read($sessionId)
    {
        // TODO: Implement read() method.
    }

    /**
     * Write session data to storage
     * @param string $sessionId
     * @param array  $data
     * @return ExtendedPromiseInterface  with session data
     */
    public function write($sessionId, $data)
    {
        return $this->writeToStorage($sessionId, $data);
    }

    /**
     * Destroy a session
     * @param string $sessionId The session ID being destroyed.
     * @return ExtendedPromiseInterface  with bool when finished
     */
    public function destroy($sessionId)
    {
        // TODO: Implement destroy() method.
    }

    /**
     * Regenerate session id
     * @param string              $sessionIdOld
     * @param AppRequestInterface $request
     * @return ExtendedPromiseInterface  with new session ID (string)
     */
    public function regenerateId($sessionIdOld, AppRequestInterface $request)
    {
        $self = $this;
        /** @var ExtendedPromiseInterface $promise */
        $promise = $this->read($sessionIdOld)->then(
            function ($data) { return $data; },
            function () { return []; }
        )->then(function ($sessionData) use ($self, $request, $sessionIdOld) {
            return $this->destroy($sessionIdOld)
                ->always(function () use ($self, $request, $sessionData) {
                    $newId = $self->createSid($request);
                    return $self->write($newId, $sessionData);
                });
        });
        return $promise;
    }

    /**
     * Cleanup old sessions. Timer callback.
     * Sessions that have not updated for the last '$this->gcLifetime' seconds will be removed.
     * @return void
     */
    public function gc()
    {
        \Reaction::$app->logger->warning(time());
        // TODO: Implement gc() method.
        $this->getArchivePath();
    }

    /**
     * Return a new session ID
     * @param AppRequestInterface $request
     * @return string  A session ID valid for session handler
     */
    public function createSid(AppRequestInterface $request)
    {
        $ip = $request->remoteIP;
        if (empty($ip)) {
            $ip = '127.0.0.1';
        }
        $time = microtime();
        try {
            $rand = \Reaction::$app->security->generateRandomString(16);
        } catch (\Exception $exception) {
            $rand = mt_rand(11111, 99999);
        }
        return md5($ip . ':' . $time . ':' . $rand);
    }

    /**
     * Update timestamp of a session
     * @param string $sessionId The session id
     * @param string $data
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * @return ExtendedPromiseInterface  with bool when finished
     */
    public function updateTimestamp($sessionId, $data)
    {
        // TODO: Implement updateTimestamp() method.
    }

    /**
     * Check session id for uniqueness
     * @param string $sessionId
     * @return bool
     */
    public function checkSessionId($sessionId)
    {
        return true;
    }

    /**
     * Serialize session data
     * @param array $sessionData
     * @return string
     */
    protected function serializeSessionData($sessionData) {
        if (is_string($sessionData)) return $sessionData;
        return Json::encode($sessionData);
    }

    /**
     * Unserialize session data
     * @param string $sessionData
     * @return array
     */
    protected function unserializeSessionData($sessionData) {
        if (is_array($sessionData)) {
            return $sessionData;
        }
        return Json::decode($sessionData);
    }

    /**
     * Create GC periodic timer
     */
    protected function createGcTimer() {
        if (isset($this->_timer) && $this->_timer instanceof TimerInterface) {
            $this->loop->cancelTimer($this->_timer);
        }
        $this->_timer = $this->loop->addPeriodicTimer($this->timerInterval, [$this, 'gc']);
    }

    /**
     * Archive session and free main storage
     * @param string $sessionId
     * @param array  $data
     * @return ExtendedPromiseInterface  with bool
     */
    public function archiveSessionData($sessionId, $data)
    {
        $filePath = $this->getArchivePath() . DIRECTORY_SEPARATOR . $sessionId;

        // TODO: Implement archiveSessionData() method.
    }

    /**
     * Restore session data from archive
     * @param string $sessionId
     * @return ExtendedPromiseInterface  with session data array or null
     */
    public function restoreSessionData($sessionId)
    {
        // TODO: Implement restoreSessionData() method.
    }

    /**
     * Get real archive path
     * @return string
     * @throws \Reaction\Exceptions\Exception
     */
    public function getArchivePath() {
        if (!isset($this->_archivePath)) {
            $this->_archivePath = \Reaction::$app->getAlias($this->archivePath);
            if (!file_exists($this->_archivePath)) {
                FileHelper::createDirectory($this->_archivePath, 0777);
            }
        }
        return $this->_archivePath;
    }

    /**
     * Write session data to storage and return last stored data
     * @param string $sessionId
     * @param array  $data
     * @return ExtendedPromiseInterface  with session data
     */
    protected function writeToStorage($sessionId, $data) {

    }
}