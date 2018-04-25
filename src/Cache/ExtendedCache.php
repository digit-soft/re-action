<?php

namespace Reaction\Cache;

use React\Promise\ExtendedPromiseInterface;
use Reaction\Base\BaseObject;
use Reaction\Helpers\Json;

/**
 * Class ExtendedCache to extend from
 * @package Reaction\Cache
 */
abstract class ExtendedCache extends BaseObject implements ExtendedCacheInterface
{
    /**
     * Get data from cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with data then finished
     */
    abstract public function get($key);

    /**
     * Write data to cache
     * @param string|array $key
     * @param mixed        $value
     * @return ExtendedPromiseInterface  with bool then finished
     */
    abstract public function set($key, $value);

    /**
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    abstract public function remove($key);

    /**
     * Remove data from cache for multiple keys
     * @param array $keys
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function removeMultiple($keys)
    {
        $promises = [];
        foreach ($keys as $key) {
            //Ensure that Promise is always fulfilled
            $promises[] = $this->remove($key)->otherwise(function () { return true; });
        }
        return \Reaction\Promise\all($promises);
    }

    /**
     * Checks that key exists in cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool
     */
    abstract public function exists($key);

    /**
     * Process key. Ensure that key is string
     * @param string|array $key
     * @return string
     */
    protected function processKey($key) {
        if (is_string($key)) {
            return $key;
        } else {
            $keyStr = Json::encode($key);
            return md5($keyStr);
        }
    }
}