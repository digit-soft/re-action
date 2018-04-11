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
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Method Not Allowed';
    }

    /**
     * Get HTTP status code
     * @return int
     */
    public function getHttpCode()
    {
        return 405;
    }
}