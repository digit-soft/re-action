<?php

namespace Reaction\Exceptions;

/**
 * Class HttpException
 * Base class for HTTP exceptions
 * @package Reaction\Exceptions
 */
class HttpException extends Exception implements HttpExceptionInterface
{
    /**
     * @return int
     */
    public function getHttpCode() {
        return 500;
    }
}