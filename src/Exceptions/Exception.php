<?php

namespace Reaction\Exceptions;

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
     * @param \Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        $message = (string)$message;
        $code = (int)$code;
        $previous = isset($previous) && $previous instanceof \Throwable ? $previous : null;
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