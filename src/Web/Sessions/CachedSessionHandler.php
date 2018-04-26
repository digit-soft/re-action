<?php

namespace Reaction\Web\Sessions;

use React\Cache\CacheInterface;
use React\Promise\ExtendedPromiseInterface;
use Reaction\Base\Component;
use Reaction\Helpers\Json;
use Reaction\Promise\Promise;
use Reaction\Web\AppRequestInterface;

/**
 * Class CachedSessionHandler
 * @package Reaction\Web\Sessions
 */
class CachedSessionHandler extends SessionHandlerAbstract
{
    /** @var string|CacheInterface */
    public $cache = 'arrayCacheDefault';
    /** @var string Key prefix for storage */
    public $keyPrefix = 'sess_';

    public $dataKey = 'data';

    public $timestampKey = 'ts';

    public function init()
    {
        if (!is_object($this->cache)) {
           $this->cache = \Reaction::create($this->cache);
        }
        parent::init();
    }

    /**
     * Read session data and returns serialized|encoded data
     * @param string $sessionId
     * @return ExtendedPromiseInterface
     */
    public function read($sessionId)
    {
        $self = $this;
        $key = $this->keyPrefix . $sessionId;
        return $this->getDataFromCache($key)->then(
            function ($record) use ($self) {
                return $self->extractData($record, true);
            },
            function (\Throwable $e) {
                if (\Reaction::isDebug()) {
                    \Reaction::$app->logger->error($e->getMessage());
                }
                return [];
            }
        );
    }

    /**
     * Write session data to storage
     * @param string $sessionId
     * @param array  $data
     * @return ExtendedPromiseInterface
     */
    public function write($sessionId, $data)
    {
        $self = $this;
        $key = $this->keyPrefix . $sessionId;
        $record = [ ];
        $record[$this->timestampKey] = time();
        $record[$this->dataKey] = $data;
        return $this->writeDataToCache($key, $record);
    }

    /**
     * Destroy a session
     * @param string $sessionId The session ID being destroyed.
     * @return ExtendedPromiseInterface
     */
    public function destroy($sessionId)
    {
        // TODO: Implement destroy() method.
    }

//    /**
//     * Cleanup old sessions. Timer callback.
//     * Sessions that have not updated for the last '$this->gcLifetime' seconds will be removed.
//     * @return void
//     */
//    public function gc()
//    {
//        // TODO: Implement gc() method.
//    }

    /**
     * Update timestamp of a session
     * @param string $sessionId The session id
     * @param string $data
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * @return bool
     */
    public function updateTimestamp($sessionId, $data)
    {
        // TODO: Implement updateTimestamp() method.
    }

    /**
     * Extract session data from record
     * @param array $sessionRecord
     * @param bool  $unserialize
     * @return array
     */
    public function extractData($sessionRecord = [], $unserialize = false) {
        $data = isset($sessionRecord[$this->dataKey]) ? $sessionRecord[$this->dataKey] : [];
        return $unserialize ? $this->unserializeSessionData($data) : $data;
    }

    /**
     * Extract session timestamp from record
     * @param array $sessionRecord
     * @return integer|null
     */
    public function extractTimestamp($sessionRecord = []) {
        return isset($sessionRecord[$this->timestampKey]) ? $sessionRecord[$this->timestampKey] : null;
    }

    /**
     * Get data from cache storage
     * @param string $key
     * @return Promise
     */
    protected function getDataFromCache($key) {
        $self = $this;
        return (new Promise(function ($r, $c) use ($self, $key) {
            $self->cache->get($key)->then(
                function ($data) use ($r, $c) {
                    $r($data);
                },
                function (\Throwable $error) use ($r, $c) {
                    if (\Reaction::isDebug()) {
                        \Reaction::$app->logger->error($error->getMessage());
                    }
                    $r(null);
                }
            );
        }));
    }

    /**
     * @param string $key
     * @param array  $sessionRecord
     * @return Promise
     */
    protected function writeDataToCache($key, $sessionRecord) {
        $data = $this->extractData($sessionRecord);
        $ts = $this->extractTimestamp($sessionRecord);
        if (null === $ts) {
            $ts = time();
        }
        $record = [];
        $record[$this->dataKey] = $this->serializeSessionData($data);
        $record[$this->timestampKey] = $ts;

        $cache = $this->cache;
        return (new Promise(function ($r, $c) use ($cache, $key, $record) {
            $cache->set($key, $record);
            $r(true);
        }));
    }
}