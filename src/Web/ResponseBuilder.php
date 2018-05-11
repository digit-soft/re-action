<?php

namespace Reaction\Web;

use Psr\Http\Message\StreamInterface;
use Reaction\Base\RequestAppComponent;
use Reaction\Exceptions\HttpException;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ArrayHelper;

/**
 * Class ResponseBuilder
 * @package Reaction\Web
 */
class ResponseBuilder extends RequestAppComponent implements ResponseBuilderInterface
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
        if (!isset($this->charset) && isset($this->app)) {
            $this->charset = $this->app->charset;
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
     * Redirects the browser to the specified URL.
     *
     * This method adds a "Location" header to the current response. Note that it does not send out
     * the header until [[send()]] is called. In a controller action you may use this method as follows:
     *
     * ```php
     * return $app->response->redirect($url);
     * ```
     *
     * In AJAX mode, this normally will not work as expected unless there are some
     * client-side JavaScript code handling the redirection. To help achieve this goal,
     * this method will send out a "X-Redirect" header instead of "Location".
     *
     * If you use the "yii" JavaScript module, it will handle the AJAX redirection as
     * described above. Otherwise, you should write the following JavaScript code to
     * handle the redirection:
     *
     * ```javascript
     * $document.ajaxComplete(function (event, xhr, settings) {
     *     var url = xhr && xhr.getResponseHeader('X-Redirect');
     *     if (url) {
     *         window.location = url;
     *     }
     * });
     * ```
     *
     * @param string|array $url the URL to be redirected to. This can be in one of the following formats:
     *
     * - a string representing a URL (e.g. "http://example.com")
     * - a string representing a URL alias (e.g. "@example.com")
     * - an array in the format of `[$route, ...name-value pairs...]` (e.g. `['site/index', 'ref' => 1]`).
     *   Note that the route is with respect to the whole application, instead of relative to a controller or module.
     *   [[Url::to()]] will be used to convert the array into a URL.
     *
     * Any relative URL that starts with a single forward slash "/" will be converted
     * into an absolute one by prepending it with the host info of the current request.
     *
     * @param int $statusCode the HTTP status code. Defaults to 302.
     * See <https://tools.ietf.org/html/rfc2616#section-10>
     * for details about HTTP status code
     * @param bool $checkAjax whether to specially handle AJAX (and PJAX) requests. Defaults to true,
     * meaning if the current request is an AJAX or PJAX request, then calling this method will cause the browser
     * to redirect to the given URL. If this is false, a `Location` header will be sent, which when received as
     * an AJAX response, may NOT cause browser redirection.
     * Takes effect only when request header `X-Ie-Redirect-Compatibility` is absent.
     * @return ResponseBuilderInterface
     */
    public function redirect($url, $statusCode = 302, $checkAjax = true)
    {
        if (is_array($url) && isset($url[0])) {
            // ensure the route is absolute
            $url[0] = '/' . ltrim($url[0], '/');
        }
        $url = $this->app->helpers->url->to($url);
        if (strncmp($url, '/', 1) === 0 && strncmp($url, '//', 2) !== 0) {
            $url = $this->app->reqHelper->getHostInfo() . $url;
        }

        if ($checkAjax) {
            if ($this->app->reqHelper->getIsAjax()) {
                if ($this->app->reqHelper->getHeaders()->get('X-Ie-Redirect-Compatibility') !== null && $statusCode === 302) {
                    // Ajax 302 redirect in IE does not work. Change status code to 200. See https://github.com/yiisoft/yii2/issues/9670
                    $statusCode = 200;
                }
                $this->getHeaders()->set('X-Redirect', $url);
            } else {
                $this->getHeaders()->set('Location', $url);
            }
        } else {
            $this->getHeaders()->set('Location', $url);
        }

        $this->setBody(null);
        $this->setStatusCode($statusCode);

        return $this;
    }

    /**
     * Refreshes the current page.
     * The effect of this method call is the same as the user pressing the refresh button of his browser
     * (without re-posting data).
     *
     * In a controller action you may use this method like this:
     *
     * ```php
     * return $app->response->refresh();
     * ```
     *
     * @param string $anchor the anchor that should be appended to the redirection URL.
     * Defaults to empty. Make sure the anchor starts with '#' if you want to specify it.
     * @return ResponseBuilderInterface
     */
    public function refresh($anchor = '')
    {
        return $this->redirect($this->app->reqHelper->getUrl() . $anchor);
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
            $request = $this->app->reqHelper;
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
     * Get default formatters
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