<?php

namespace Reaction\Cache;

use React\Promise\ExtendedPromiseInterface;
use Reaction\Exceptions\Exception;
use Reaction\Helpers\ArrayHelper;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

class ArrayExpiringCache extends ExpiringCache
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
            $data = $this->unpackRecord($this->storage[$key]);
            return resolve($data);
        }
        return resolve($default);
    }

    /**
     * Write data to cache
     * @param string|array $key Cache key
     * @param mixed        $value Data
     * @param integer|null $ttl Cache lifetime in seconds
     * @param array        $tags  Possible data tags
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value, $ttl = null, $tags = [])
    {
        $ttl = isset($ttl) ? $ttl : $this->lifetimeDefault;
        $expire = time() + $ttl;
        $key = $this->processKey($key);
        $record = $this->packRecord($value);
        $this->addKeyTags($key, $record, $tags);
        $this->storage[$key] = $record;
        $this->_timestamps[$key] = $expire;
        return resolve(true);
    }

    /**
     * Remove data from cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    public function delete($key)
    {
        $key = $this->processKey($key);
        if ($this->existInternal($key)) {
            $tags = $this->storage[$key][$this->tagsKey];
            unset($this->storage[$key]);
            unset($this->_timestamps[$key]);
            foreach ($tags as $tag) {
                unset($this->tags[$tag][$key]);
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
        return reject(new Exception(sprintf('Cache for key "%s" not exists', $key)));
    }

    /**
     * Check that data exists in storage array
     * @param string $key
     * @return bool
     */
    protected function existInternal($key) {
        return ArrayHelper::keyExists($key, $this->storage);
    }

    /**
     * Save key tags
     * @param string   $key
     * @param array    $record
     * @param string[] $tags
     */
    protected function addKeyTags($key, &$record = [], $tags = [])
    {
        $record[$this->tagsKey] = $tags;
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][$key] = $key;
        }
    }
}