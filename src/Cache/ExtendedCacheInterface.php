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
     * Retrieves an item from the cache.
     *
     * This method will resolve with the cached value on success or with the
     * given `$default` value when no item can be found or when an error occurs.
     * Similarly, an expired cache item (once the time-to-live is expired) is
     * considered a cache miss.
     *
     * ```php
     * $cache
     *     ->get('foo')
     *     ->then('var_dump');
     * ```
     *
     * This example fetches the value of the key `foo` and passes it to the
     * `var_dump` function. You can use any of the composition provided by
     * [promises](https://github.com/reactphp/promise).
     *
     * @param string|array $key String key
     * @param mixed        $default Default value to return for cache miss or null if not given.
     * @return ExtendedPromiseInterface  with data
     */
    public function get($key, $default = null);

    /**
     * Stores an item in the cache.
     *
     * This method will resolve with `true` on success or `false` when an error
     * occurs. If the cache implementation has to go over the network to store
     * it, it may take a while.
     *
     * The optional `$ttl` parameter sets the maximum time-to-live in seconds
     * for this cache item. If this parameter is omitted (or `null`), the item
     * will stay in the cache for as long as the underlying implementation
     * supports. Trying to access an expired cache item results in a cache miss,
     * see also [`get()`](#get).
     *
     * The optional `$tags` parameter sets some cache tags if not empty.
     *
     * ```php
     * $cache->set('foo', 'bar', 60);
     * ```
     *
     * This example eventually sets the value of the key `foo` to `bar`. If it
     * already exists, it is overridden.
     *
     *
     * @param string|array $key Cache key
     * @param mixed        $value Data to store
     * @param float        $ttl time-to-live
     * @param array        $tags Possible data tags
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value, $ttl = null, $tags = []);

    /**
     * Deletes an item from the cache.
     *
     * This method will resolve with `true` on success or `false` when an error
     * occurs. When no item for `$key` is found in the cache, it also resolves
     * to `true`. If the cache implementation has to go over the network to
     * delete it, it may take a while.
     *
     * ```php
     * $cache->delete('foo');
     * ```
     *
     * This example eventually deletes the key `foo` from the cache. As with
     * `set()`, this may not happen instantly and a promise is returned to
     * provide guarantees whether or not the item has been removed from cache.
     *
     * @param string|array $key
     * @return ExtendedPromiseInterface  with `bool` then finished
     */
    public function delete($key);

    /**
     * Delete data from cache for multiple keys
     * @param array $keys
     * @return ExtendedPromiseInterface  with `bool` then finished
     * @see delete()
     */
    public function deleteMultiple($keys);

    /**
     * Delete cache data by tag
     * @param string $tag
     * @return ExtendedPromiseInterface  with `bool` then finished
     * @see delete()
     */
    public function deleteByTag($tag);

    /**
     * Checks that key exists in cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with `bool`
     */
    public function exists($key);

    /**
     * Flush all cache
     * @return ExtendedPromiseInterface with `bool` then finished
     */
    public function flush();
}