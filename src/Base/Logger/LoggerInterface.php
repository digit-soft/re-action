<?php

namespace Reaction\Base\Logger;


interface LoggerInterface extends \Psr\Log\LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function emergency($message, array $context = array(), $traceShift = 0);

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function alert($message, array $context = array(), $traceShift = 0);

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function critical($message, array $context = array(), $traceShift = 0);

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function error($message, array $context = array(), $traceShift = 0);

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function warning($message, array $context = array(), $traceShift = 0);

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function notice($message, array $context = array(), $traceShift = 0);

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function info($message, array $context = array(), $traceShift = 0);

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function debug($message, array $context = array(), $traceShift = 0);

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @param int $traceShift
     *
     * @return void
     */
    public function log($level, $message, array $context = array(), $traceShift = 0);
}