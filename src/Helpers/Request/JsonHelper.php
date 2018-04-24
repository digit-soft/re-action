<?php

namespace Reaction\Helpers\Request;

use Reaction\Base\Model;
use Reaction\Exceptions\InvalidArgumentException;

/**
 * Class JsonHelper. Proxy to \Reaction\Helpers\Json
 * @package Reaction\Web\RequestComponents
 */
class JsonHelper extends RequestHelperProxy
{
    public $helperClass = 'Reaction\Helpers\Json';

    /**
     * Encodes the given value into a JSON string.
     *
     * The method enhances `json_encode()` by supporting JavaScript expressions.
     * In particular, the method will not encode a JavaScript expression that is
     * represented in terms of a [[JsExpression]] object.
     *
     * Note that data encoded as JSON must be UTF-8 encoded according to the JSON specification.
     * You must ensure strings passed to this method have proper encoding before passing them.
     *
     * @param mixed $value the data to be encoded.
     * @param int $options the encoding options. For more details please refer to
     * <http://www.php.net/manual/en/function.json-encode.php>. Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
     * @return string the encoding result.
     * @throws InvalidArgumentException if there is any encoding error.
     */
    public function encode($value, $options = 320)
    {
        return $this->proxy(__FUNCTION__, [$value, $options]);
    }

    /**
     * Encodes the given value into a JSON string HTML-escaping entities so it is safe to be embedded in HTML code.
     *
     * The method enhances `json_encode()` by supporting JavaScript expressions.
     * In particular, the method will not encode a JavaScript expression that is
     * represented in terms of a [[JsExpression]] object.
     *
     * Note that data encoded as JSON must be UTF-8 encoded according to the JSON specification.
     * You must ensure strings passed to this method have proper encoding before passing them.
     *
     * @param mixed $value the data to be encoded
     * @return string the encoding result
     * @throws InvalidArgumentException if there is any encoding error
     */
    public function htmlEncode($value)
    {
        return $this->proxy(__FUNCTION__, [$value]);
    }

    /**
     * Decodes the given JSON string into a PHP data structure.
     * @param string $json the JSON string to be decoded
     * @param bool $asArray whether to return objects in terms of associative arrays.
     * @return mixed the PHP data
     * @throws InvalidArgumentException if there is any decoding error
     */
    public function decode($json, $asArray = true)
    {
        return $this->proxy(__FUNCTION__, [$json, $asArray]);
    }

    /**
     * Generates a summary of the validation errors.
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - showAllErrors: boolean, if set to true every error message for each attribute will be shown otherwise
     *   only the first error message for each attribute will be shown. Defaults to `false`.
     *
     * @return string the generated error summary
     */
    public function errorSummary($models, $options = [])
    {
        return $this->proxy(__FUNCTION__, [$models, $options]);
    }
}