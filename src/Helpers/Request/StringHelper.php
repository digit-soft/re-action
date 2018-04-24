<?php

namespace Reaction\Helpers\Request;

/**
 * Class StringHelper. Proxy To \Reaction\Helpers\StringHelper
 * @package Reaction\Web\RequestComponents
 */
class StringHelper extends RequestHelperProxy
{
    public $helperClass = 'Reaction\Helpers\StringHelper';

    /**
     * Returns the number of bytes in the given string.
     * This method ensures the string is treated as a byte array by using `mb_strlen()`.
     * @param string $string the string being measured for length
     * @return int the number of bytes in the given string.
     */
    public function byteLength($string)
    {
        return $this->proxy(__FUNCTION__, [$string]);
    }

    /**
     * Returns the portion of string specified by the start and length parameters.
     * This method ensures the string is treated as a byte array by using `mb_substr()`.
     * @param string $string the input string. Must be one character or longer.
     * @param int $start the starting position
     * @param int $length the desired portion length. If not specified or `null`, there will be
     * no limit on length i.e. the output will be until the end of the string.
     * @return string the extracted part of string, or FALSE on failure or an empty string.
     * @see http://www.php.net/manual/en/function.substr.php
     */
    public function byteSubstr($string, $start, $length = null)
    {
        return $this->proxy(__FUNCTION__, [$string, $start, $length]);
    }

    /**
     * Returns the trailing name component of a path.
     * This method is similar to the php function `basename()` except that it will
     * treat both \ and / as directory separators, independent of the operating system.
     * This method was mainly created to work on php namespaces. When working with real
     * file paths, php's `basename()` should work fine for you.
     * Note: this method is not aware of the actual filesystem, or path components such as "..".
     *
     * @param string $path A path string.
     * @param string $suffix If the name component ends in suffix this will also be cut off.
     * @return string the trailing name component of the given path.
     * @see http://www.php.net/manual/en/function.basename.php
     */
    public function basename($path, $suffix = '')
    {
        return $this->proxy(__FUNCTION__, [$path, $suffix]);
    }

    /**
     * Returns parent directory's path.
     * This method is similar to `dirname()` except that it will treat
     * both \ and / as directory separators, independent of the operating system.
     *
     * @param string $path A path string.
     * @return string the parent directory's path.
     * @see http://www.php.net/manual/en/function.basename.php
     */
    public function dirname($path)
    {
        return $this->proxy(__FUNCTION__, [$path]);
    }

    /**
     * Truncates a string to the number of characters specified.
     *
     * @param string $string The string to truncate.
     * @param int $length How many characters from original string to include into truncated string.
     * @param string $suffix String to append to the end of truncated string.
     * @param string $encoding The charset to use, defaults to charset currently used by application.
     * @param bool $asHtml Whether to treat the string being truncated as HTML and preserve proper HTML tags.
     * @return string the truncated string.
     */
    public function truncate($string, $length, $suffix = '...', $encoding = null, $asHtml = false)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$string, $length, $suffix, $encoding, $asHtml], -2);
    }

    /**
     * Truncates a string to the number of words specified.
     *
     * @param string $string The string to truncate.
     * @param int $count How many words from original string to include into truncated string.
     * @param string $suffix String to append to the end of truncated string.
     * @param bool $asHtml Whether to treat the string being truncated as HTML and preserve proper HTML tags.
     * @return string the truncated string.
     */
    public function truncateWords($string, $count, $suffix = '...', $asHtml = false)
    {
        return $this->proxy(__FUNCTION__, [$string, $count, $suffix, $asHtml]);
    }

    /**
     * Truncate a string while preserving the HTML.
     *
     * @param string $string The string to truncate
     * @param int $count
     * @param string $suffix String to append to the end of the truncated string.
     * @param string|bool $encoding
     * @return string
     */
    protected function truncateHtml($string, $count, $suffix, $encoding = false)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$string, $count, $suffix, $encoding]);
    }

    /**
     * Check if given string starts with specified substring.
     * Binary and multibyte safe.
     *
     * @param string $string Input string
     * @param string $with Part to search inside the $string
     * @param bool   $caseSensitive Case sensitive search. Default is true. When case sensitive is enabled, $with must exactly match the starting of the string in order to get a true value.
     * @param string $encoding String encoding
     * @return bool Returns true if first input starts with second input, false otherwise
     */
    public function startsWith($string, $with, $caseSensitive = true, $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$string, $with, $caseSensitive, $encoding]);
    }

    /**
     * Check if given string ends with specified substring.
     * Binary and multibyte safe.
     *
     * @param string $string Input string to check
     * @param string $with Part to search inside of the $string.
     * @param bool $caseSensitive Case sensitive search. Default is true. When case sensitive is enabled, $with must exactly match the ending of the string in order to get a true value.
     * @param string $encoding String encoding
     * @return bool Returns true if first input ends with second input, false otherwise
     */
    public function endsWith($string, $with, $caseSensitive = true, $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$string, $with, $caseSensitive, $encoding]);
    }

    /**
     * Explodes string into array, optionally trims values and skips empty ones.
     *
     * @param string $string String to be exploded.
     * @param string $delimiter Delimiter. Default is ','.
     * @param mixed $trim Whether to trim each element. Can be:
     *   - boolean - to trim normally;
     *   - string - custom characters to trim. Will be passed as a second argument to `trim()` function.
     *   - callable - will be called for each value instead of trim. Takes the only argument - value.
     * @param bool $skipEmpty Whether to skip empty strings between delimiters. Default is false.
     * @return array
     */
    public function explode($string, $delimiter = ',', $trim = true, $skipEmpty = false)
    {
        return $this->proxy(__FUNCTION__, [$string, $delimiter, $trim, $skipEmpty]);
    }

    /**
     * Counts words in a string.
     *
     * @param string $string
     * @return int
     */
    public function countWords($string)
    {
        return $this->proxy(__FUNCTION__, [$string]);
    }

    /**
     * Returns string representation of number value with replaced commas to dots, if decimal point
     * of current locale is comma.
     * @param int|float|string $value
     * @return string
     */
    public function normalizeNumber($value)
    {
        return $this->proxy(__FUNCTION__, [$value]);
    }

    /**
     * Encodes string into "Base 64 Encoding with URL and Filename Safe Alphabet" (RFC 4648).
     *
     * > Note: Base 64 padding `=` may be at the end of the returned string.
     * > `=` is not transparent to URL encoding.
     *
     * @see https://tools.ietf.org/html/rfc4648#page-7
     * @param string $input the string to encode.
     * @return string encoded string.
     */
    public function base64UrlEncode($input)
    {
        return $this->proxy(__FUNCTION__, [$input]);
    }

    /**
     * Decodes "Base 64 Encoding with URL and Filename Safe Alphabet" (RFC 4648).
     *
     * @see https://tools.ietf.org/html/rfc4648#page-7
     * @param string $input encoded string.
     * @return string decoded string.
     */
    public function base64UrlDecode($input)
    {
        return $this->proxy(__FUNCTION__, [$input]);
    }

    /**
     * Safely casts a float to string independent of the current locale.
     *
     * The decimal separator will always be `.`.
     * @param float|int $number a floating point number or integer.
     * @return string the string representation of the number.
     */
    public function floatToString($number)
    {
        return $this->proxy(__FUNCTION__, [$number]);
    }

    /**
     * Checks if the passed string would match the given shell wildcard pattern.
     * This function emulates [[fnmatch()]], which may be unavailable at certain environment, using PCRE.
     * @param string $pattern the shell wildcard pattern.
     * @param string $string the tested string.
     * @param array $options options for matching. Valid options are:
     *
     * - caseSensitive: bool, whether pattern should be case sensitive. Defaults to `true`.
     * - escape: bool, whether backslash escaping is enabled. Defaults to `true`.
     * - filePath: bool, whether slashes in string only matches slashes in the given pattern. Defaults to `false`.
     *
     * @return bool whether the string matches pattern or not.
     */
    public function matchWildcard($pattern, $string, $options = [])
    {
        return $this->proxy(__FUNCTION__, [$pattern, $string, $options]);
    }
}