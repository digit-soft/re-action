<?php

namespace Reaction\Exceptions;

/**
 * Class Error
 * @package Reaction\Exceptions
 */
class Error extends Exception
{
    /**
     * Get Exception name
     * @return string
     */
    public function getName() {
        return 'General error';
    }
}