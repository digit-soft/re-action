<?php

namespace Reaction\Exceptions\Http;

use Reaction\Exceptions\HttpException;

/**
 * Class InternalServerErrorException
 * @package Reaction\Exceptions\Http
 */
class InternalServerErrorException extends HttpException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Internal Server Error';
    }

    /**
     * Get HTTP status code
     * @return int
     */
    public function getHttpCode()
    {
        return 500;
    }
}