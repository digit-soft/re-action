<?php

namespace Reaction\Exceptions;

/**
 * Interface HttpExceptionInterface
 * @package app\base\exceptions
 */
interface HttpExceptionInterface extends \Throwable
{
    /**
     * Get HTTP status code
     * @return integer
     */
    public function getHttpCode();
}