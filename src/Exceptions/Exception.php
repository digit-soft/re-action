<?php

namespace Reaction\Exceptions;
use Throwable;

/**
 * Class Exception
 * @package Reaction\Exceptions
 */
class Exception extends \Exception
{
    /**
     * Exception constructor.
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get Exception name
     * @return string
     */
    public function getName() {
        return 'Exception';
    }
}