<?php

namespace Reaction\Exceptions;

/**
 * ViewNotFoundException represents an exception caused by view file not found.
 */
class ViewNotFoundException extends InvalidArgumentException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'View not Found';
    }
}
