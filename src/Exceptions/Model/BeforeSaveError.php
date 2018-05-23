<?php

namespace Reaction\Exceptions\Model;

/**
 * Class BeforeSaveError
 * @package Reaction\Exceptions\Model
 */
class BeforeSaveError extends Error
{
    /**
     * Get Exception name
     * @return string
     */
    public function getName() {
        return 'Model ::beforeSave Error';
    }
}