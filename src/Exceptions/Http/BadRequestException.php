<?php

namespace Reaction\Exceptions\Http;

use Reaction\Exceptions\HttpException;

/**
 * Class BadRequestException
 * @package Reaction\Exceptions\Http
 */
class BadRequestException extends HttpException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Bad Request';
    }

    /**
     * Get HTTP status code
     * @return int
     */
    public function getHttpCode()
    {
        return 400;
    }
}