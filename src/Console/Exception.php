<?php

namespace Reaction\Console;

/**
 * Class Exception
 * @package Reaction\Console
 */
class Exception extends \Reaction\Exceptions\Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Error';
    }
}