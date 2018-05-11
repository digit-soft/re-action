<?php

namespace Reaction\Cache;

use React\Promise\ExtendedPromiseInterface;
use Reaction\Helpers\ArrayHelper;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

class ArrayExpiringCache extends ExpiringCache
{
    /** @var array Data storage */
    protected $storage = [];

    /**
     * Get data from cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with data then finished
     */
    public function get($key)
    {
        $key = $this->processKey($key);
        if ($this->existInternal($key)) {
            $data = $this->unpackRecord($this->storage[$key]);
            return resolve($data);
        }
        return reject(null);
    }

    /**
     * Write data to cache
     * @param string|array $key
     * @param mixed        $value
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value)
    {
        $key = $this->processKey($key);
        $record = $this->packRecord($value);
        $this->storage[$key] = $record;
        return resolve(true);
    }

    /**
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function remove($key)
    {
        $key = $this->processKey($key);
        if ($this->existInternal($key)) {
            unset($this->storage[$key]);
        }
        return resolve(true);
    }

    /**
     * Checks that key exists in cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool
     */
    public function exists($key)
    {
        $key = $this->processKey($key);
        if ($this->existInternal($key)) {
            return resolve(true);
        }
        return resolve(false);
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function existInternal($key) {
        return ArrayHelper::keyExists($key, $this->storage);
    }
}