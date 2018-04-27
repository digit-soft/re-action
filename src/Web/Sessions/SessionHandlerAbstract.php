<?php

namespace Reaction\Web\Sessions;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Filesystem\Node\FileInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use React\Stream\WritableStreamInterface;
use Reaction\Base\Component;
use Reaction\Exceptions\SessionException;
use Reaction\Helpers\Json;
use Reaction\Web\AppRequestInterface;
use function Reaction\Promise\resolve;

abstract class SessionHandlerAbstract extends Component implements SessionHandlerInterface
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

    /** @var TimerInterface Timer for GC */
    protected $_timer;
    /** @var string Archive saving path */
    protected $_archivePath;
    /** @var bool Flag that garbage collector not finished his work */
    protected $_gcIsRunning = false;

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
     * @param string $id
     * @return PromiseInterface  with session data
     */
    abstract public function read($id);

    /**
     * Write session data to storage
     * @param string $id
     * @param array  $data
     * @return PromiseInterface  with session data
     */
    abstract public function write($id, $data);

    /**
     * Destroy a session
     * @param string $id The session ID being destroyed.
     * @return PromiseInterface  with bool when finished
     */
    abstract public function destroy($id);

    /**
     * Regenerate session id
     * @param string              $idOld
     * @param AppRequestInterface $request
     * @param bool                $deleteOldSession
     * @return PromiseInterface  with new session ID (string)
     */
    public function regenerateId($idOld, AppRequestInterface $request, $deleteOldSession = false)
    {
        $self = $this;
        $dataOld = [];
        /** @var ExtendedPromiseInterface $promise */
        $promise = $this->read($idOld)->then(
            function ($data) { return $data; },
            function () { return []; }
        )->then(function ($data) use ($idOld, &$dataOld) {
            $dataOld = $data;
            return $this->destroy($idOld)->then(null, function () { return true; });
        })->then(
            function () use ($self, $request) {
                return $self->createId($request);
            }
        )->then(
            function ($newId) use ($self, &$dataOld, $deleteOldSession) {
                $retCallback = function () use ($newId) { return $newId; };
                $dataNew = $deleteOldSession ? [] : $dataOld;
                return $self->write($newId, $dataNew)->then($retCallback, $retCallback);
            },
            function () use ($idOld) {
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
     * @see $sessionLifetime
     * @return void
     */
    abstract public function gc();

    /**
     * Return a new session ID
     * @param AppRequestInterface $request
     * @return string  A session ID valid for session handler
     */
    public function createId(AppRequestInterface $request)
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
        $id = md5($ip . ':' . $time . ':' . $rand);
        $self = $this;
        return $this->checkSessionId($id)->then(
            function () use($id) { return $id; },
            function () use ($self, $request) { return $self->createId($request); }
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
     * @return PromiseInterface  with bool when finished
     */
    abstract public function updateTimestamp($id, $data);

    /**
     * Check session id for uniqueness
     * @param string $sessionId
     * @return PromiseInterface
     */
    public function checkSessionId($sessionId)
    {
        return resolve(true);
    }

    /**
     * Archive session and free main storage
     * @param string $sessionId
     * @param array  $data
     * @return PromiseInterface  with bool
     */
    public function archiveSessionData($sessionId, $data)
    {
        $dataSr = $this->serializeSessionData($data);
        /** @var FileInterface $file */
        $file = null;
        $writePromise = $this->getArchiveFilePath($sessionId)->then(
            function ($filePath) use (&$file) {
                $file = \Reaction::$app->fs->file($filePath);
                return \Reaction::$app->fs->file($filePath)->exists();
            }
        )->then(null,
            function () use (&$file) { return $file->create(); }
        )->then(
            function () use (&$file) { return $file->chmod(0777); }
        )->then(
            function () use (&$file) { return $file->open('wf'); }
        )->then(
            function (WritableStreamInterface $stream) use ($dataSr) {
                $stream->write($dataSr);
                $stream->close();
                return true;
            }
        )->then(null, function ($error = null) {
            $message = $error instanceof \Throwable
                ? $error->getMessage() . "\n" . $error->getFile() . ' #' . $error->getLine() : $error;
            \Reaction::$app->logger->error($message);
            return false;
        });
        return $writePromise;
    }

    /**
     * Restore session data from archive
     * @param string $sessionId
     * @return PromiseInterface  with session data array or null
     */
    public function restoreSessionData($sessionId)
    {
        $self = $this;
        return $this->getArchiveFilePath($sessionId)->then(
            function ($filePath) {
                $file = \Reaction::$app->fs->file($filePath);
                return $file->getContents();
            }
        )->then(
            function ($dataStr) use ($self) {
                try {
                    $data = $self->unserializeSessionData($dataStr);
                } catch (\InvalidArgumentException $exception) {
                    $data = null;
                }
                return is_array($data) ? $data : null;
            },
            function () use ($sessionId) {
                $message = sprintf('Failed to restore session "%s"', $sessionId);
                throw new SessionException($message);
            }
        );
    }

    /**
     * Remove session from archive
     * @param string $sessionId
     * @return PromiseInterface
     */
    protected function removeFromArchive($sessionId) {
        /** @var FileInterface $file */
        $file = null;
        return $this->getArchiveFilePath($sessionId)->then(
            function ($filePath) use (&$file) {
                $file = \Reaction::$app->fs->file($filePath);
                return $file->exists();
            }
        )->then(function () use (&$file) {
            return $file->remove();
        })->then(function () {
            return true;
        }, function () use ($sessionId) {
            $message = sprintf('Failed to remove session "%s"', $sessionId);
            throw new SessionException($message);
        });
    }

    /**
     * Get real archive path
     * @return PromiseInterface
     */
    protected function getArchivePath() {
        if (!isset($this->_archivePath)) {
            $path = \Reaction::$app->getAlias($this->archivePath);
            $self = $this;
            $fs = \Reaction::$app->fs;
            $dir = $fs->dir($path);
            $dirAsFile = $fs->file($path);
            return $dirAsFile->exists()->then(
                null,
                function () use ($dir) { return $dir->createRecursive(); }
            )->then(
                function () use ($dir) { return $dir->chmodRecursive(0777); }
            )->then(
                function () use ($self, $path) {
                    $self->_archivePath = $path;
                    return $path;
                },
                function () {
                    throw new SessionException("Failed to get session archive path");
                }
            );
        }
        return resolve($this->_archivePath);
    }

    /**
     * Get session archive file path
     * @param string $sessionId
     * @return PromiseInterface
     */
    protected function getArchiveFilePath($sessionId) {
        $fileName = $sessionId . '.json';
        return $this->getArchivePath()->then(
            function ($dirPath) use ($fileName) {
                return rtrim($dirPath) . DIRECTORY_SEPARATOR . $fileName;
            }
        );
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
}