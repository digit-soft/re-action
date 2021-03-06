<?php

namespace Reaction\I18n;

/**
 * Interface MessageSourceInterface
 * @package Reaction\I18n
 */
interface MessageSourceInterface
{
    /**
     * Translates a message to the specified language.
     *
     * Note that unless [[forceTranslation]] is true, if the target language
     * is the same as the [[sourceLanguage|source language]], the message
     * will NOT be translated.
     *
     * If a translation is not found, a [[EVENT_MISSING_TRANSLATION|missingTranslation]] event will be triggered.
     *
     * @param string $category the message category
     * @param string $message the message to be translated
     * @param string $language the target language
     * @return string|bool the translated message or false if translation wasn't found or isn't required
     */
    public function translate($category, $message, $language);

    /**
     * Preload all messages from source
     * @param string $category
     * @param array $languages
     * @return \Reaction\Promise\ExtendedPromiseInterface
     */
    public function preloadMessages($category, $languages = []);
}