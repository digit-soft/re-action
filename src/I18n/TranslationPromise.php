<?php

namespace Reaction\I18n;

use Reaction;

/**
 * Class TranslationPromise
 * @package Reaction\I18n
 */
class TranslationPromise
{
    public $category;
    public $message;
    public $params;
    public $language;

    public function __construct($category, $message, $params = [], $language = null)
    {
        $this->category = $category;
        $this->message = $message;
        $this->params = $params;
        if (isset($language)) {
            $this->language = $language;
        }
    }

    /**
     * Translate message to given language.
     * Or suggest language by backtrace
     * @param string|null $language
     * @return string
     */
    public function translate($language = null)
    {
        if (isset($language)) {
            $this->language = $language;
        } else {
            $this->suggestLanguage();
        }
        return Reaction::t($this->category, $this->message, $this->params, $this->language);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->translate();
        } catch (\Throwable $exception) {
            Reaction::error($exception->getMessage() . "\n" . $exception->getFile() . ' #' . $exception->getLine());
            return '';
        }
    }

    /**
     * Dirty method to suggest translation language
     */
    protected function suggestLanguage()
    {
        if (Reaction::isDebug()) {
            Reaction::warning(__METHOD__ . ' call. Avoid it for performance reasons.');
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        $traceCount = count($trace) - 1;
        for ($i = $traceCount; $i >= 0; $i--) {
            if (!isset($trace[$i]['object']) || !$trace[$i]['object'] instanceof RequestLanguageGetterInterface) {
                continue;
            }
            /** @var RequestLanguageGetterInterface $object */
            $object = $trace[$i]['object'];
            $this->language = $object->getRequestLanguage();
            break;
        }
    }
}