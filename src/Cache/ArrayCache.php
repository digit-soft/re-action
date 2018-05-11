<?php

namespace Reaction\Cache;

use React\Promise\ExtendedPromiseInterface;
use Reaction\Helpers\ArrayHelper;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * Class ArrayCache
 * @package Reaction\Cache
 */
class ArrayCache extends ExtendedCache
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
            return resolve($this->storage[$key]);
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
        $this->storage[$key] = $value;
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
     * Internal check that record exists in storage
     * @internal
     * @param string $key
     * @return bool
     */
    protected function existInternal($key) {
        return ArrayHelper::keyExists($key, $this->storage);
    }
}