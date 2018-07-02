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
    /** @var array Tags associated with data keys */
    protected $tags = [];

    /**
     * Get data from cache
     * @param string|array $key
     * @param mixed        $default Default value to return for cache miss or null if not given.
     * @return ExtendedPromiseInterface  with data then finished
     */
    public function get($key, $default = null)
    {
        $key = $this->processKey($key);
        if ($this->existInternal($key)) {
            return resolve($this->storage[$key]);
        }
        return resolve($default);
    }

    /**
     * Write data to cache
     * @param string|array $key
     * @param mixed        $value
     * @param array        $tags  Possible data tags
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value, $tags = [])
    {
        $key = $this->processKey($key);
        $this->storage[$key] = $value;
        $this->addKeyTags($key, $tags);
        return resolve(true);
    }

    /**
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function delete($key)
    {
        $key = $this->processKey($key);
        if ($this->existInternal($key)) {
            unset($this->storage[$key]);
            foreach ($this->tags as $tag => $keys) {
                if (isset($keys[$key])) {
                    unset($this->tags[$tag][$key]);
                }
            }
        }
        return resolve(true);
    }

    /**
     * Remove cache data by tag
     * @param string $tag
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function deleteByTag($tag)
    {
        if (!isset($this->tags[$tag])) {
            return resolve(true);
        }
        $keys = $this->tags[$tag];
        unset($this->tags[$tag]);
        return !empty($keys) ? $this->deleteMultiple($keys) : resolve(true);
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

    /**
     * Save key tags
     * @param string   $key
     * @param string[] $tags
     */
    protected function addKeyTags($key, $tags = [])
    {
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][$key] = $key;
        }
    }
}