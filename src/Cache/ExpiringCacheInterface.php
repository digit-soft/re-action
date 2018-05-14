<?php

namespace Reaction\Cache;

use React\EventLoop\LoopInterface;
use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Interface ExpiringCacheInterface
 * @package Reaction\Cache
 * @property LoopInterface $loop
 * @property integer       $timerInterval
 * @property string        $dataKey
 * @property string        $timestampKey
 */
interface ExpiringCacheInterface extends ExtendedCacheInterface
{
    /**
     * Write data to cache
     * @param string|array $key Cache key
     * @param mixed        $value Data
     * @param integer|null $lifetime Cache lifetime in seconds
     * @param array        $tags Possible tags
     * @return ExtendedPromiseInterface  with bool then finished
     */
    public function set($key, $value, $lifetime = null, $tags = []);
}