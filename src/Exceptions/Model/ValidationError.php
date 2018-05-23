<?php

namespace Reaction\Exceptions\Model;

use Reaction\Exceptions\Exception;

/**
 * Class ValidationException
 * @package Reaction\Exceptions
 */
class ValidationError extends Exception
{
    public $errors = [];

    /**
     * Get Exception name
     * @return string
     */
    public function getName() {
        return 'Model Validation Error';
    }

    /**
     * Set validation errors
     * @param array $errors
     */
    public function setErrors($errors) {
        $this->errors = (array)$errors;
    }
}