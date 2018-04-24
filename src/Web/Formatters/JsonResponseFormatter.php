<?php

namespace Reaction\Web\Formatters;

use Reaction;
use Reaction\Base\Component;
use Reaction\Helpers\Json;
use Reaction\Web\ResponseBuilderInterface;

/**
 * JsonResponseFormatter formats the given data into a JSON or JSONP response content.
 *
 * It is used by [[Response]] to format response data.
 *
 * To configure properties like [[encodeOptions]] or [[prettyPrint]], you can configure the `response`
 * application component like the following:
 *
 * ```php
 * 'response' => [
 *     // ...
 *     'formatters' => [
 *         \Reaction\Web\Response::FORMAT_JSON => [
 *              'class' => 'yii\web\JsonResponseFormatter',
 *              'prettyPrint' => YII_DEBUG, // use "pretty" output in debug mode
 *              // ...
 *         ],
 *     ],
 * ],
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class JsonResponseFormatter extends Component implements ResponseFormatterInterface
{
    /**
     * JSON Content Type
     * @since 2.0.14
     */
    const CONTENT_TYPE_JSONP = 'application/javascript; charset=UTF-8';
    /**
     * JSONP Content Type
     * @since 2.0.14
     */
    const CONTENT_TYPE_JSON = 'application/json; charset=UTF-8';
    /**
     * HAL JSON Content Type
     * @since 2.0.14
     */
    const CONTENT_TYPE_HAL_JSON = 'application/hal+json; charset=UTF-8';

    /**
     * @var string|null custom value of the `Content-Type` header of the response.
     * When equals `null` default content type will be used based on the `useJsonp` property.
     * @since 2.0.14
     */
    public $contentType;
    /**
     * @var bool whether to use JSONP response format. When this is true, the [[Response::data|response data]]
     * must be an array consisting of `data` and `callback` members. The latter should be a JavaScript
     * function name while the former will be passed to this function as a parameter.
     */
    public $useJsonp = false;
    /**
     * @var int the encoding options passed to [[Json::encode()]]. For more details please refer to
     * <http://www.php.net/manual/en/function.json-encode.php>.
     * Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
     * This property has no effect, when [[useJsonp]] is `true`.
     * @since 2.0.7
     */
    public $encodeOptions = 320;
    /**
     * @var bool whether to format the output in a readable "pretty" format. This can be useful for debugging purpose.
     * If this is true, `JSON_PRETTY_PRINT` will be added to [[encodeOptions]].
     * Defaults to `false`.
     * This property has no effect, when [[useJsonp]] is `true`.
     * @since 2.0.7
     */
    public $prettyPrint = false;


    /**
     * Formats the specified response.
     * @param ResponseBuilderInterface $response the response to be formatted.
     */
    public function format($response)
    {
        if ($this->contentType === null) {
            $this->contentType = $this->useJsonp
                ? self::CONTENT_TYPE_JSONP
                : self::CONTENT_TYPE_JSON;
        } elseif (strpos($this->contentType, 'charset') === false) {
            $this->contentType .= '; charset=UTF-8';
        }
        $response->getHeaders()->set('Content-Type', $this->contentType);
  
        if ($this->useJsonp) {
            return $this->formatJsonp($response);
        } else {
            return $this->formatJson($response);
        }
    }

    /**
     * Formats response data in JSON format.
     * @param ResponseBuilderInterface $response
     * @return array|\Psr\Http\Message\StreamInterface|string
     */
    protected function formatJson($response)
    {
        $bodyRaw = $response->getRawBody();
        $request = $response->request;
        if ($bodyRaw !== null) {
            $options = $this->encodeOptions;
            if ($this->prettyPrint) {
                $options |= JSON_PRETTY_PRINT;
            }
            return $request instanceof Reaction\Web\RequestInterface
                ? $request->helpers->json->encode($bodyRaw, $options)
                : Json::encode($bodyRaw, $options);
        }
        return '';
    }

    /**
     * Formats response data in JSONP format.
     * @param ResponseBuilderInterface $response
     * @return string
     */
    protected function formatJsonp($response)
    {
        $bodyRaw = $response->getRawBody();
        $request = $response->request;
        if (is_array($bodyRaw)
            && isset($bodyRaw['data'], $bodyRaw['callback'])
        ) {
            $data = $request instanceof Reaction\Web\RequestInterface
                ? $request->helpers->json->htmlEncode($bodyRaw['data'])
                : Json::htmlEncode($bodyRaw['data']);
            return sprintf('%s(%s);', $bodyRaw['callback'], $data);
        } elseif ($bodyRaw !== null) {
            Reaction::$app->logger->warning(
                "The 'jsonp' response requires that the data be an array consisting of both 'data' and 'callback' elements.",
                __METHOD__
            );
        }
        return '';
    }
}
