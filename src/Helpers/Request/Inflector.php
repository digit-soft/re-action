<?php

namespace Reaction\Helpers\Request;

use Reaction\Helpers\Inflector as InflectorStatic;

/**
 * Class Inflector. Proxy to \Reaction\Helpers\Inflector
 * @package Reaction\Web\RequestComponents
 */
class Inflector extends RequestHelperProxy
{
    public $helperClass = 'Reaction\Helpers\Inflector';

    /**
     * Converts a word to its plural form.
     * Note that this is for English only!
     * For example, 'apple' will become 'apples', and 'child' will become 'children'.
     * @param string $word the word to be pluralized
     * @return string the pluralized word
     * @see \Reaction\Helpers\Inflector::pluralize()
     */
    public function pluralize($word)
    {
        return $this->proxy(__FUNCTION__, [$word]);
    }

    /**
     * Returns the singular of the $word.
     * @param string $word the english word to singularize
     * @return string Singular noun.
     * @see \Reaction\Helpers\Inflector::singularize()
     */
    public function singularize($word)
    {
        return $this->proxy(__FUNCTION__, [$word]);
    }

    /**
     * Converts an underscored or CamelCase word into a English
     * sentence.
     * @param string $words
     * @param bool $ucAll whether to set all words to uppercase
     * @return string
     * @see \Reaction\Helpers\Inflector::titleize()
     */
    public function titleize($words, $ucAll = false)
    {
        return $this->proxy(__FUNCTION__, [$words, $ucAll]);
    }

    /**
     * Returns given word as CamelCased.
     *
     * Converts a word like "send_email" to "SendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "WhoSOnline".
     * @see variablize()
     * @param string $word the word to CamelCase
     * @return string
     * @see \Reaction\Helpers\Inflector::camelize()
     */
    public function camelize($word)
    {
        return $this->proxy(__FUNCTION__, [$word]);
    }

    /**
     * Converts a CamelCase name into space-separated words.
     * For example, 'PostTag' will be converted to 'Post Tag'.
     * @param string $name the string to be converted
     * @param bool $ucwords whether to capitalize the first letter in each word
     * @return string the resulting words
     * @see \Reaction\Helpers\Inflector::camel2words()
     */
    public function camel2words($name, $ucwords = true)
    {
        return $this->proxy(__FUNCTION__, [$name, $ucwords]);
    }

    /**
     * Converts a CamelCase name into an ID in lowercase.
     * Words in the ID may be concatenated using the specified character (defaults to '-').
     * For example, 'PostTag' will be converted to 'post-tag'.
     * @param string $name the string to be converted
     * @param string $separator the character used to concatenate the words in the ID
     * @param bool|string $strict whether to insert a separator between two consecutive uppercase chars, defaults to false
     * @return string the resulting ID
     * @see \Reaction\Helpers\Inflector::camel2id()
     */
    public function camel2id($name, $separator = '-', $strict = false)
    {
        return $this->proxy(__FUNCTION__, [$name, $separator, $strict]);
    }

    /**
     * Converts an ID into a CamelCase name.
     * Words in the ID separated by `$separator` (defaults to '-') will be concatenated into a CamelCase name.
     * For example, 'post-tag' is converted to 'PostTag'.
     * @param string $id the ID to be converted
     * @param string $separator the character used to separate the words in the ID
     * @return string the resulting CamelCase name
     * @see \Reaction\Helpers\Inflector::id2camel()
     */
    public function id2camel($id, $separator = '-')
    {
        return $this->proxy(__FUNCTION__, [$id, $separator]);
    }

    /**
     * Converts any "CamelCased" into an "underscored_word".
     * @param string $words the word(s) to underscore
     * @return string
     * @see \Reaction\Helpers\Inflector::underscore()
     */
    public function underscore($words)
    {
        return $this->proxy(__FUNCTION__, [$words]);
    }

    /**
     * Returns a human-readable string from $word.
     * @param string $word the string to humanize
     * @param bool $ucAll whether to set all words to uppercase or not
     * @return string
     * @see \Reaction\Helpers\Inflector::humanize()
     */
    public function humanize($word, $ucAll = false)
    {
        return $this->proxy(__FUNCTION__, [$word, $ucAll]);
    }

    /**
     * Same as camelize but first char is in lowercase.
     *
     * Converts a word like "send_email" to "sendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "whoSOnline".
     * @param string $word to lowerCamelCase
     * @return string
     * @see \Reaction\Helpers\Inflector::variablize()
     */
    public function variablize($word)
    {
        return $this->proxy(__FUNCTION__, [$word]);
    }

    /**
     * Converts a class name to its table name (pluralized) naming conventions.
     *
     * For example, converts "Person" to "people".
     * @param string $className the class name for getting related table_name
     * @return string
     * @see \Reaction\Helpers\Inflector::tableize()
     */
    public function tableize($className)
    {
        return $this->proxy(__FUNCTION__, [$className]);
    }

    /**
     * Returns a string with all spaces converted to given replacement,
     * non word characters removed and the rest of characters transliterated.
     *
     * If intl extension isn't available uses fallback that converts latin characters only
     * and removes the rest. You may customize characters map via $transliteration property
     * of the helper.
     *
     * @param string $string An arbitrary string to convert
     * @param string $replacement The replacement to use for spaces
     * @param bool $lowercase whether to return the string in lowercase or not. Defaults to `true`.
     * @return string The converted string.
     * @see \Reaction\Helpers\Inflector::slug()
     */
    public function slug($string, $replacement = '-', $lowercase = true)
    {
        return $this->proxy(__FUNCTION__, [$string, $replacement, $lowercase]);
    }

    /**
     * Returns transliterated version of a string.
     *
     * If intl extension isn't available uses fallback that converts latin characters only
     * and removes the rest. You may customize characters map via $transliteration property
     * of the helper.
     *
     * @param string $string input string
     * @param string|\Transliterator $transliterator either a [[\Transliterator]] or a string
     * from which a [[\Transliterator]] can be built.
     * @return string
     * @see \Reaction\Helpers\Inflector::transliterate()
     */
    public function transliterate($string, $transliterator = null)
    {
        return $this->proxy(__FUNCTION__, [$string, $transliterator]);
    }

    /**
     * @return bool if intl extension is loaded
     */
    protected function hasIntl()
    {
        return $this->proxy(__FUNCTION__);
    }

    /**
     * Converts a table name to its class name.
     *
     * For example, converts "people" to "Person".
     * @param string $tableName
     * @return string
     * @see \Reaction\Helpers\Inflector::classify()
     */
    public function classify($tableName)
    {
        return $this->proxy(__FUNCTION__, [$tableName]);
    }

    /**
     * Converts number to its ordinal English form. For example, converts 13 to 13th, 2 to 2nd ...
     * @param int $number the number to get its ordinal value
     * @return string
     * @see \Reaction\Helpers\Inflector::ordinalize()
     */
    public function ordinalize($number)
    {
        return $this->proxy(__FUNCTION__, [$number]);
    }

    /**
     * Converts a list of words into a sentence.
     *
     * Special treatment is done for the last few words. For example,
     *
     * ```php
     * $words = ['Spain', 'France'];
     * echo Inflector::sentence($words);
     * // output: Spain and France
     *
     * $words = ['Spain', 'France', 'Italy'];
     * echo Inflector::sentence($words);
     * // output: Spain, France and Italy
     *
     * $words = ['Spain', 'France', 'Italy'];
     * echo Inflector::sentence($words, ' & ');
     * // output: Spain, France & Italy
     * ```
     *
     * @param array $words the words to be converted into an string
     * @param string $twoWordsConnector the string connecting words when there are only two
     * @param string $lastWordConnector the string connecting the last two words. If this is null, it will
     * take the value of `$twoWordsConnector`.
     * @param string $connector the string connecting words other than those connected by
     * $lastWordConnector and $twoWordsConnector
     * @return string the generated sentence
     * @see \Reaction\Helpers\Inflector::sentence()
     */
    public function sentence(array $words, $twoWordsConnector = null, $lastWordConnector = null, $connector = ', ')
    {
        return $this->proxy(__FUNCTION__, [$words, $twoWordsConnector, $lastWordConnector, $connector]);
    }
}