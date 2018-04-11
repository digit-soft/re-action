<?php

namespace Reaction\Exceptions\Http;

use Reaction\Exceptions\HttpException;

/**
 * Class ForbiddenException
 * @package Reaction\Exceptions\Http
 */
class ForbiddenException extends HttpException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Forbidden';
    }

    /**
     * Get HTTP status code
     * @return int
     */
    public function getHttpCode()
    {
        return 403;
    }
}