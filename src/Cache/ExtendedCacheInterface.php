<?php

namespace Reaction\Cache;

use React\Cache\CacheInterface;
use React\Promise\ExtendedPromiseInterface;

/**
 * Interface ExtendedCacheInterface
 * @package Reaction\Cache
 */
interface ExtendedCacheInterface extends CacheInterface
{
    /**
     * Get data from cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with data
     */
    public function get($key);

    /**
     * Write data to cache
     * @param string|array $key
     * @param mixed $value
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value);

    /**
     * Remove data from cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function remove($key);

    /**
     * Remove data from cache for multiple keys
     * @param array $keys
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function removeMultiple($keys);

    /**
     * Checks that key exists in cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool
     */
    public function exists($key);
}