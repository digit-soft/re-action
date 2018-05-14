<?php

namespace Reaction\Cache;

use React\Cache\CacheInterface;
use Reaction\Promise\ExtendedPromiseInterface;

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
     * @param string|array $key Cache key
     * @param mixed        $value Data to store
     * @param array        $tags  Possible data tags
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value, $tags = []);

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
     * Remove cache data by tag
     * @param string $tag
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function removeByTag($tag);

    /**
     * Checks that key exists in cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool
     */
    public function exists($key);
}