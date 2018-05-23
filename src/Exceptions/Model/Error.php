<?php

namespace Reaction\Exceptions\Model;

use Reaction\Exceptions\Exception;

/**
 * Class Error
 * @package Reaction\Exceptions\Model
 */
class Error extends Exception
{
    /**
     * Get Exception name
     * @return string
     */
    public function getName() {
        return 'Model general error';
    }
}