<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Reaction\Web\Formatters;

use Psr\Http\Message\StreamInterface;
use Reaction\Base\Component;
use Reaction\Web\ResponseBuilderInterface;
use Reaction\Web\ResponseFormatterInterface;

/**
 * HtmlResponseFormatter formats the given data into an HTML response content.
 *
 * It is used by [[Response]] to format response data.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HtmlResponseFormatter extends Component implements ResponseFormatterInterface
{
    /**
     * @var string the Content-Type header for the response
     */
    public $contentType = 'text/html';

    /**
     * Formats the specified response.
     * @param ResponseBuilderInterface $responseBuilder the response to be formatted.
     * @return \Psr\Http\Message\StreamInterface|string
     */
    public function format($responseBuilder)
    {
        if (stripos($this->contentType, 'charset') === false) {
            $this->contentType .= '; charset=' . $responseBuilder->charset;
        }
        $responseBuilder->getHeaders()->set('Content-Type', $this->contentType);
        $body = $responseBuilder->getRawBody();
        if (!is_string($body) && !($body instanceof StreamInterface)) {
            $body = print_r($body, true);
        }
        return $body;
    }
}
