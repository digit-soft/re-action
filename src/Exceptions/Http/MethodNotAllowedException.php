<?php

namespace Reaction\Exceptions\Http;

use Reaction\Exceptions\HttpException;

/**
 * Class MethodNotAllowedException
 * @package Reaction\Exceptions\Http
 */
class MethodNotAllowedException extends HttpException
{
    /**
     * Constructor.
     * @param string $message error message
     * @param int $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(405, $message, $code, $previous);
    }
}