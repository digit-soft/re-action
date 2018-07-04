<?php

namespace Reaction\Cache;

use Reaction\Exceptions\Exception;
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function set($key, $value, $ttl = null, $tags = [])
    {
        $key = $this->processKey($key);
        $this->storage[$key] = $value;
        $this->addKeyTags($key, $tags);
        return resolve(true);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function flush()
    {
        $this->storage = [];
        $this->tags = [];
        return resolve(true);
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