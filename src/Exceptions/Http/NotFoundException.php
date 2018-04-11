<?php

namespace Reaction\Exceptions\Http;

use Reaction\Exceptions\HttpException;

/**
 * Class NotFoundException
 * @package Reaction\Exceptions\Http
 */
class NotFoundException extends HttpException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Not Found';
    }

    /**
     * Get HTTP status code
     * @return int
     */
    public function getHttpCode()
    {
        return 404;
    }
}