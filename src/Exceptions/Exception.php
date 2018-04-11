<?php

namespace Reaction\Exceptions;

/**
 * Class Exception
 * @package Reaction\Exceptions
 */
class Exception extends \Exception
{
    /**
     * Get Exception name
     * @return string
     */
    public function getName() {
        return 'Exception';
    }
}