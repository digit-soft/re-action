<?php

namespace Reaction\Cache;

use Reaction\Base\BaseObject;
use Reaction\Helpers\Json;
use Reaction\Helpers\VarDumper;
use function Reaction\Promise\all;

/**
 * Class ExtendedCache to extend from
 * @package Reaction\Cache
 */
abstract class ExtendedCache extends BaseObject implements ExtendedCacheInterface
{
    /**
     * @inheritdoc
     */
    abstract public function get($key, $default = null);

    /**
     * @inheritdoc
     */
    abstract public function set($key, $value, $ttl = null, $tags = []);

    /**
     * @inheritdoc
     */
    abstract public function delete($key);

    /**
     * @inheritdoc
     */
    public function deleteMultiple($keys)
    {
        $promises = [];
        foreach ($keys as $key) {
            //Ensure that Promise is always fulfilled
            $promises[] = $this->delete($key)->otherwise(function() { return true; });
        }
        return all($promises)->then(function() { return true; });
    }

    /**
     * @inheritdoc
     */
    abstract public function exists($key);

    /**
     * @inheritdoc
     */
    abstract public function deleteByTag($tag);

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

    /**
     * Serialize data to string
     * @param array $data
     * @return string
     */
    protected function serializeData($data)
    {
        return VarDumper::serialize($data);
    }

    /**
     * Unserialize data from string
     * @param string $dataSerialized
     * @return mixed
     */
    protected function unserializeData($dataSerialized)
    {
        return VarDumper::unserialize($dataSerialized);
    }
}