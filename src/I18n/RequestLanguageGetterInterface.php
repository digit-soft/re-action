<?php

namespace Reaction\I18n;

/**
 * Interface RequestLanguageGetterInterface
 * @package Reaction\I18n
 */
interface RequestLanguageGetterInterface
{
    /**
     * Get language for current request
     * @return string
     */
    public function getRequestLanguage();
}