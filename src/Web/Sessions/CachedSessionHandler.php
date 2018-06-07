<?php

namespace Reaction\Web\Sessions;

use Reaction\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use Reaction\Cache\ExtendedCacheInterface;
use Reaction\Exceptions\SessionException;
use Reaction\Promise\Promise;

/**
 * Class CachedSessionHandler
 * @package Reaction\Web\Sessions
 */
class CachedSessionHandler extends SessionHandlerAbstract
{
    /** @var string|ExtendedCacheInterface */
    public $cache = 'arrayCacheDefault';
    /** @var string Key prefix for storage */
    public $keyPrefix = 'sess_';

    /** @var string Array key where user data is located */
    public $dataKey = '_data';
    /** @var string Array key where timestamp is located */
    public $timestampKey = '_ts';
    /** @var array Session keys with timestamp */
    protected $keys = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (is_string($this->cache) || is_array($this->cache)) {
            $this->cache = \Reaction::create($this->cache);
        } elseif (!$this->cache instanceof ExtendedCacheInterface) {
            $message = sprintf("Property `cache` must be set with instance of `%s` or configuration array|string", ExtendedCacheInterface::class);
            throw new SessionException($message);
        }
        parent::init();
    }

    /**
     * Read session data and returns serialized|encoded data
     * @param string $id
     * @return ExtendedPromiseInterface  with session data
     */
    public function read($id)
    {
        $key = $this->getSessionKey($id);
        return $this->getDataFromCache($key)->then(
            function ($record) {
                return $this->extractData($record, true);
            }
        )->then(
            null,
            function ($error = null) use ($id) {
                //return $self->restoreSessionData($id, true)->then(
                return $this->archive->get($id)->then(
                    function ($data) use ($id, $error) {
                        if (is_array($data)) {
                            return $this->write($id, $data);
                        } else {
                            return \Reaction\Promise\reject($error);
                        }
                    }
                );
            }
        )->then(
            null,
            function ($error = null) use ($id) {
                $message = sprintf('Failed to read session data for "%s"', $id);
                throw new SessionException($message, 0, $error);
            }
        );
    }

    /**
     * Write session data to storage
     * @param string $id
     * @param array  $data
     * @return ExtendedPromiseInterface  with session data
     */
    public function write($id, $data)
    {
        $key = $this->getSessionKey($id);
        $record = $this->packRecord($data);
        return $this->writeDataToCache($key, $record)->then(
            function () use ($id) {
                $this->keys[$id] = time();
                return $this->read($id);
            },
            function ($error = null) use ($id) {
                $message = sprintf('Failed to write session data for "%s"', $id);
                throw new SessionException($message, 0, $error);
            }
        );
    }

    /**
     * Destroy a session
     * @param string $id The session ID being destroyed.
     * @param bool   $archiveRemove Remove data from archive or no
     * @return ExtendedPromiseInterface with bool when finished
     */
    public function destroy($id, $archiveRemove = false)
    {
        if (isset($this->keys[$id])) {
            unset($this->keys[$id]);
        }
        $key = $this->getSessionKey($id);
        return $this->cache->remove($key)->then(
            function () use ($id) { return $id; },
            function ($error = null) use ($id) {
                $message = sprintf('Failed to destroy session "%s"', $id);
                throw new SessionException($message, 0, $error);
            }
        )->always(function() use ($id, $archiveRemove) {
            return $archiveRemove ? $this->archive->remove($id) : true;
        });
    }

    /**
     * Cleanup old sessions. Timer callback.
     * Sessions that have not updated for the last '$this->gcLifetime' seconds will be archived.
     * Sessions in archive with age bigger than '$this->sessionLifetime' seconds will be removed.
     * @see $gcLifetime
     * @see $gcArchiveLifetime
     * @return void
     */
    public function gc()
    {
        $promises = [];
        if (!$this->_gcIsRunning) {
            $this->_gcIsRunning = true;
            if (!empty($this->keys)) {
                $expiredTime = time() - $this->gcLifetime;
                foreach ($this->keys as $id => $ts) {
                    if ($ts > $expiredTime) {
                        continue;
                    }
                    $promise = $this->read($id)->then(
                        function ($data) use ($id) {
                            return $this->archive->set($id, $data);
                        }
                    )->then(
                        function () use ($id) {
                            return $this->destroy($id, false);
                        }
                    );
                    $promises[] = $promise;
                }
            }
            $promises[] = $this->archive->gc($this->gcArchiveLifetime);
        }
        if (!empty($promises)) {
            \Reaction\Promise\all($promises)->always(
                function () {
                    $this->_gcIsRunning = false;
                }
            );
        } else {
            $this->_gcIsRunning = false;
        }
    }

    /**
     * Update timestamp of a session
     * @param string $id The session id
     * @param array  $data
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * @return ExtendedPromiseInterface  with bool when finished
     */
    public function updateTimestamp($id, $data = null)
    {
        $self = $this;
        return $this->read($id)->then(
            function ($dataStored) use ($self, $id, $data) {
                if (isset($data)) {
                    $dataStored = $data;
                }
                return $self->write($id, $dataStored);
            }
        )->then(
            function () { return true; }
        );
    }

    /**
     * Get cache session key
     * @param string $sessionId
     * @return string
     */
    protected function getSessionKey($sessionId) {
        return $this->keyPrefix . trim($sessionId);
    }


    /**
     * Extract session data from record
     * @param array $sessionRecord
     * @param bool  $unserialize
     * @return array
     */
    public function extractData($sessionRecord = [], $unserialize = false) {
        $data = isset($sessionRecord[$this->dataKey]) ? $sessionRecord[$this->dataKey] : [];
        return $unserialize && is_string($data) ? $this->unserializeData($data) : $data;
    }

    /**
     * Extract session timestamp from record
     * @param array $record
     * @return integer|null
     */
    public function extractTimestamp($record = []) {
        return isset($record[$this->timestampKey]) ? $record[$this->timestampKey] : null;
    }

    /**
     * Get data from cache storage
     * @param string $key
     * @return ExtendedPromiseInterface
     */
    protected function getDataFromCache($key) {
        $self = $this;
        return (new Promise(function ($r, $c) use ($self, $key) {
            $self->cache->get($key)->then(
                function ($data) use ($r, $c) {
                    $r($data);
                },
                function ($error = null) use (&$c, $key) {
                    $message = sprintf('Failed to get session data from cache for "%s"', $key);
                    $c(new SessionException($message));
                }
            );
        }));
    }

    /**
     * @param string $key
     * @param array  $record
     * @return ExtendedPromiseInterface
     */
    protected function writeDataToCache($key, $record) {
        $ts = $this->extractTimestamp($record);
        $record[$this->timestampKey] = isset($ts) ? $ts : time();

        $cache = $this->cache;
        return new Promise(function ($r, $c) use ($cache, $key, $record) {
            $cache->set($key, $record)->then(
                function () use ($r) {
                    $r(true);
                },
                function ($error = null) use (&$c, $key) {
                    $message = sprintf('Failed to write session data to cache for "%s"', $key);
                    $c(new SessionException($message));
                }
            );
        });
    }

    /**
     * Pack record
     * @param array $data
     * @return array
     */
    protected function packRecord($data) {
        $record = [];
        $dKey = $this->dataKey;
        $tKey = $this->timestampKey;
        if (isset($data[$tKey])) {
            unset($data[$tKey]);
        }
        if (isset($data[$dKey])) {
            $record[$dKey] = $data[$dKey];
        } else {
            $record[$dKey] = $data;
        }
        $record[$this->timestampKey] = time();
        $record[$dKey] = !is_string($record[$dKey])
            ? $this->serializeData($record[$dKey])
            : $record[$dKey];
        return $record;
    }
}