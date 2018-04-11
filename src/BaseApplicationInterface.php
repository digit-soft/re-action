<?php

namespace Reaction;

/**
 * Interface BaseApplicationInterface
 * @package Reaction
 * @property string $charset
 * @property \React\EventLoop\LoopInterface     $loop
 * @property \React\Http\Server                 $http
 * @property \React\Socket\Server               $socket
 */
interface BaseApplicationInterface
{
    /**
     * Run application
     */
    public function run();
}