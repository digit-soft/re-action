<?php

namespace Reaction\Cache;

use React\EventLoop\LoopInterface;

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
}