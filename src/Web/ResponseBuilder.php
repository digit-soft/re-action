<?php

namespace Reaction\Web;

use Psr\Http\Message\StreamInterface;
use Reaction\Exceptions\HttpException;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ArrayHelper;

/**
 * Class ResponseBuilder
 * @package Reaction\Web
 */
class ResponseBuilder extends RequestComponent implements ResponseBuilderInterface
{
    /**
     * @var string|array Response class config array or class name
     */
    public $responseClass = 'Reaction\Web\Response';
    /**
     * @var string HTTP version
     */
    public $version = '1.1';
    /**
     * @var string the charset of the text response. If not set, it will use
     * the value of [[BaseApplication::charset]].
     */
    public $charset;
    /**
     * @var string the response format. This determines how to convert [[data]] into [[content]]
     * when the latter is not set. The value of this property must be one of the keys declared in the [[formatters]] array.
     * By default, the following formats are supported:
     *
     * - [[FORMAT_RAW]]: the data will be treated as the response content without any conversion.
     *   No extra HTTP header will be added.
     * - [[FORMAT_HTML]]: the data will be treated as the response content without any conversion.
     *   The "Content-Type" header will set as "text/html".
     * - [[FORMAT_JSON]]: the data will be converted into JSON format, and the "Content-Type"
     *   header will be set as "application/json".
     * - [[FORMAT_JSONP]]: the data will be converted into JSONP format, and the "Content-Type"
     *   header will be set as "text/javascript". Note that in this case `$data` must be an array
     *   with "data" and "callback" elements. The former refers to the actual data to be sent,
     *   while the latter refers to the name of the JavaScript callback.
     * - [[FORMAT_XML]]: the data will be converted into XML format. Please refer to [[XmlResponseFormatter]]
     *   for more details.
     *
     * You may customize the formatting process or support additional formats by configuring [[formatters]].
     * @see formatters
     */
    public $format = Response::FORMAT_HTML;
    /**
     * @var array the formatters for converting data into the response content of the specified [[format]].
     * The array keys are the format names, and the array values are the corresponding configurations
     * for creating the formatter objects.
     * @see format
     * @see defaultFormatters
     */
    public $formatters = [];

    /**
     * @var int Status code
     */
    protected $_statusCode = 200;
    /**
     * @var int Status text
     */
    protected $_statusText = 'OK';
    /**
     * @var CookieCollection Collection of request cookies.
     */
    protected $_cookies;
    /**
     * @var HeaderCollection Collection of request headers.
     */
    protected $_headers;
    /**
     * @var string|array|StreamInterface Response body
     */
    protected $_body;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!isset($this->charset) && isset($this->request)) {
            $this->charset = $this->request->charset;
        }
        $this->formatters = array_merge($this->defaultFormatters(), $this->formatters);
    }

    /**
     * Get status code
     * @return int
     */
    public function getStatusCode() {
        return $this->_statusCode;
    }

    /**
     * Set status code
     * @param int $code
     * @return ResponseBuilderInterface
     */
    public function setStatusCode($code = 200) {
        $this->_statusCode = $code;
        $this->_statusText = isset(Response::$httpStatuses[$this->_statusCode]) ? Response::$httpStatuses[$this->_statusCode] : '';
        return $this;
    }

    /**
     * Sets the response status code based on the exception.
     * @param \Exception|\Error $e the exception object.
     * @throws InvalidArgumentException if the status code is invalid.
     * @return ResponseBuilderInterface the response object itself
     */
    public function setStatusCodeByException($e)
    {
        if ($e instanceof HttpException) {
            $this->setStatusCode($e->statusCode);
        } else {
            $this->setStatusCode(500);
        }

        return $this;
    }

    /**
     * Set Response format
     * @param string $format
     * @return ResponseBuilderInterface
     */
    public function setFormat($format) {
        $this->format = $format;
        return $this;
    }

    /**
     * Set Response body
     * @param string|array|StreamInterface $body
     * @return ResponseBuilderInterface
     */
    public function setBody($body) {
        $this->_body = $body;
        return $this;
    }

    /**
     * Get Response body without formatting
     * @return array|StreamInterface|string
     */
    public function getRawBody() {
        return $this->_body;
    }

    /**
     * Get Response body with formatting
     * @return array|null|StreamInterface|string
     * @throws InvalidConfigException
     */
    public function getFormattedBody() {
        $body = $this->getRawBody();
        $bodyFormatted = null;
        if ($body instanceof StreamInterface) {
            return $body;
        } elseif (isset($this->formatters[$this->format])) {
            $formatter = $this->formatters[$this->format];
            if (!is_object($formatter)) {
                $this->formatters[$this->format] = $formatter = \Reaction::create($formatter);
            }
            if ($formatter instanceof ResponseFormatterInterface) {
                $bodyFormatted = $formatter->format($this);
            } else {
                throw new InvalidConfigException("The '{$this->format}' response formatter is invalid. It must implement the ResponseFormatterInterface.");
            }
        } elseif ($this->format === Response::FORMAT_RAW) {
            $bodyFormatted = $body;
        } else {
            throw new InvalidConfigException("Unsupported response format: {$this->format}");
        }

        if (is_array($bodyFormatted)) {
            throw new InvalidArgumentException('Response content must not be an array.');
        } elseif (is_object($bodyFormatted)) {
            if (method_exists($bodyFormatted, '__toString')) {
                $bodyFormatted = $bodyFormatted->__toString();
            } else {
                throw new InvalidArgumentException('Response content must be a string or an object implementing __toString().');
            }
        }
        return $bodyFormatted;
    }

    /**
     * Get Response body and optionally format it
     * @param bool $format
     * @return array|StreamInterface|string
     * @throws InvalidConfigException
     */
    public function getBody($format = false) {
        if ($format) {
            return $this->getFormattedBody();
        }
        return $this->_body;
    }

    /**
     * Returns the header collection.
     * The header collection contains the currently registered HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection();
        }

        return $this->_headers;
    }

    /**
     * Returns the cookie collection.
     *
     * Through the returned cookie collection, you add or remove cookies as follows,
     *
     * ```php
     * // add a cookie
     * $response->cookies->add(new Cookie([
     *     'name' => $name,
     *     'value' => $value,
     * ]);
     *
     * // remove a cookie
     * $response->cookies->remove('name');
     * // alternatively
     * unset($response->cookies['name']);
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection();
        }

        return $this->_cookies;
    }

    /**
     * Build response object
     * @return Response
     */
    public function build() {
        $response = $this->createEmptyResponse();

        return $response;
    }

    /**
     * Create Response instance
     * @return Response
     */
    protected function createEmptyResponse() {
        $config = [];
        $body = $this->getFormattedBody();
        $params = [
            $this->statusCode,
            $this->getHeadersPrepared(),
            $body,
            $this->version,
            $this->_statusText
        ];
        if (is_array($this->responseClass)) {
            $config = ArrayHelper::merge($config, $this->responseClass);
        } else {
            $config['class'] = $this->responseClass;
        }
        /** @var Response $response */
        $response = \Reaction::create($config, $params);
        return $response;
    }

    /**
     * Get prepared headers array
     * @return array
     * @throws InvalidConfigException
     */
    protected function getHeadersPrepared() {
        $cookiesData = $this->getCookiesPrepared();
        foreach ($cookiesData as $cookieStr) {
            $this->headers->add('Set-Cookie', $cookieStr);
        }
        $headersArray = [];
        if ($this->_headers) {
            foreach ($this->getHeaders() as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                if (!isset($headersArray[$name])) {
                    $headersArray[$name] = [];
                }
                foreach ($values as $value) {
                    $headersArray[$name][] = $value;
                }
            }
        }
        return $headersArray;
    }

    /**
     * Get cookies prepared for 'Set-Cookie' header
     * @return string[]
     * @throws InvalidConfigException
     */
    protected function getCookiesPrepared() {
        $cookies = [];
        if ($this->_cookies) {
            $request = $this->request;
            if ($request->enableCookieValidation) {
                if ($request->cookieValidationKey == '') {
                    throw new InvalidConfigException(get_class($request) . '::cookieValidationKey must be configured with a secret key.');
                }
                $validationKey = $request->cookieValidationKey;
            } else {
                $validationKey = null;
            }
            foreach ($this->getCookies() as $name => $cookie) {
                /** @var Cookie $cookie */
                $cookies[] = $cookie->getForHeader($validationKey);
            }
        }

        return $cookies;
    }

    protected function prepareBody() {

    }

    /**
     * @return array the formatters that are supported by default
     */
    protected function defaultFormatters()
    {
        return [
            Response::FORMAT_HTML => [
                'class' => 'Reaction\Web\Formatters\HtmlResponseFormatter',
            ],
            Response::FORMAT_XML => [
                'class' => 'Reaction\Web\Formatters\XmlResponseFormatter',
            ],
            Response::FORMAT_JSON => [
                'class' => 'Reaction\Web\Formatters\JsonResponseFormatter',
            ],
            Response::FORMAT_JSONP => [
                'class' => 'Reaction\Web\Formatters\JsonResponseFormatter',
                'useJsonp' => true,
            ],
        ];
    }
}