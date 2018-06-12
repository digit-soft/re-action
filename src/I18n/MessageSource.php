<?php

namespace Reaction\I18n;

use Reaction;
use Reaction\Base\Component;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\all;
use function Reaction\Promise\resolve;

/**
 * MessageSource is the base class for message translation repository classes.
 *
 * A message source stores message translations in some persistent storage.
 *
 * Child classes should override [[loadMessages()]] to provide translated messages.
 */
class MessageSource extends Component implements MessageSourceInterface
{
    /**
     * @event MissingTranslationEvent an event that is triggered when a message translation is not found.
     */
    const EVENT_MISSING_TRANSLATION = 'missingTranslation';

    /**
     * @var bool whether to force message translation when the source and target languages are the same.
     * Defaults to false, meaning translation is only performed when source and target languages are different.
     */
    public $forceTranslation = false;
    /**
     * @var string the language that the original messages are in. If not set, it will use the value of
     * [[\Reaction\StaticApplicationInterface::sourceLanguage]].
     */
    public $sourceLanguage;
    /**
     * @var array Messages loaded. Indexed by key (language / category)
     */
    private $_messages = [];


    /**
     * Initializes this component.
     */
    public function init()
    {
        parent::init();
        if ($this->sourceLanguage === null) {
            $this->sourceLanguage = Reaction::$app->sourceLanguage;
        }
    }

    /**
     * Preload all messages from source
     * @param string $category Category or wildcard
     * @param array $languages Languages used to preload
     * @return ExtendedPromiseInterface
     */
    public function preloadMessages($category, $languages = [])
    {
        if ($category === '*') {
            $categoriesFind = $this->findAllCategories();
        } else {
            $categoriesFind = strpos($category, '*') > 0 ? $this->findCategoriesByPattern($category) : resolve([$category]);
        }
        return $categoriesFind
            ->otherwise(function() { return []; })
            ->then(function($categories) use ($languages) {
                $promises = [];
                foreach ($categories as $category) {
                    foreach ($languages as $language) {
                        $key = $language . '/' . $category;
                        $promises[$key] = $this->loadMessages($category, $language);
                    }
                }
                if (empty($promises)) {
                    return [];
                }
                return all($promises)
                    ->then(function($results) {
                        $this->_messages = Reaction\Helpers\ArrayHelper::merge($this->_messages, $results);
                        return $results;
                    });
            });
    }

    /**
     * Find categories by string pattern|wildcard
     * @param string $pattern
     * @return ExtendedPromiseInterface
     */
    protected function findCategoriesByPattern($pattern)
    {
        return $this->findAllCategories()
            ->then(function($categories) use ($pattern) {
                $matches = [];
                foreach ($categories as $category) {
                    if (Reaction\Helpers\StringHelper::matchWildcard($pattern, $category)) {
                        $matches[] = $category;
                    }
                }
                return $matches;
            });
    }

    /**
     * Find all categories used in message source
     * @return ExtendedPromiseInterface
     */
    protected function findAllCategories()
    {
        return resolve([]);
    }

    /**
     * Loads the message translation for the specified language and category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return ExtendedPromiseInterface with array the loaded messages. The keys are original messages, and the values
     * are translated messages.
     */
    protected function loadMessages($category, $language)
    {
        return resolve([]);
    }

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
    public function translate($category, $message, $language)
    {
        if ($this->forceTranslation || $language !== $this->sourceLanguage) {
            return $this->translateMessage($category, $message, $language);
        }

        return false;
    }

    /**
     * Translates the specified message.
     * If the message is not found, a [[EVENT_MISSING_TRANSLATION|missingTranslation]] event will be triggered.
     * If there is an event handler, it may provide a [[MissingTranslationEvent::$translatedMessage|fallback translation]].
     * If no fallback translation is provided this method will return `false`.
     * @param string $category the category that the message belongs to.
     * @param string $message the message to be translated.
     * @param string $language the target language.
     * @return string|bool the translated message or false if translation wasn't found.
     */
    protected function translateMessage($category, $message, $language)
    {
        $key = $language . '/' . $category;
        //No load on run, all messages must be preloaded
        //if (!isset($this->_messages[$key])) {
        //    $this->_messages[$key] = $this->loadMessages($category, $language);
        //}
        if (isset($this->_messages[$key][$message]) && $this->_messages[$key][$message] !== '') {
            return $this->_messages[$key][$message];
        } elseif ($this->hasEventListeners(self::EVENT_MISSING_TRANSLATION)) {
            $translation = null;
            $this->emit(self::EVENT_MISSING_TRANSLATION, [$this, $category, $language, $message, &$translation]);
            if ($translation !== null) {
                return $this->_messages[$key][$message] = $translation;
            }
        }

        return $this->_messages[$key][$message] = false;
    }
}
