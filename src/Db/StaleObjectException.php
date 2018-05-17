<?php

namespace Reaction\Db;

use Reaction\Exceptions\Exception;

/**
 * Class StaleObjectException
 * @package Reaction\Db
 */
class StaleObjectException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Stale Object Exception';
    }
}
