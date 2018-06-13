<?php

namespace Reaction\I18n;

/**
 * Interface TranslatorInterface
 * @package Reaction\I18n
 */
interface TranslatorInterface
{
    /**
     * Translates a message to the specified language.
     *
     * @param string $category Message category
     * @param string $message  Message for translation
     * @param array  $params   Parameters array
     * @param string $language Language translate to
     * @return string
     */
    public function t($category, $message, $params = [], $language = null);
}