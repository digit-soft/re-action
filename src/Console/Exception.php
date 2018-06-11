<?php

namespace Reaction\Console;

/**
 * Class Exception
 * @package Reaction\Console
 */
class Exception extends \Reaction\Exceptions\UserException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Console exception';
    }
}