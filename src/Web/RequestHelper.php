<?php

namespace Reaction\Web;

use Psr\Http\Message\ServerRequestInterface;
use Reaction;
use Reaction\Base\RequestAppComponent;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Validators\IpValidator;

/**
 * Class RequestHelper
 * @package Reaction\Web
 * @property ServerRequestInterface $reactRequest
 * @property string                 $pathInfo
 */
class RequestHelper extends RequestAppComponent
{
    /**
     * The name of the HTTP header for sending CSRF token.
     */
    const CSRF_HEADER = 'X-CSRF-Token';

    /**
     * @var bool whether to enable CSRF (Cross-Site Request Forgery) validation. Defaults to true.
     * When CSRF validation is enabled, forms submitted to an Yii Web application must be originated
     * from the same application. If not, a 400 HTTP exception will be raised.
     *
     * Note, this feature requires that the user client accepts cookie. Also, to use this feature,
     * forms submitted via POST method must contain a hidden input whose name is specified by [[csrfParam]].
     * You may use [[\yii\helpers\Html::beginForm()]] to generate his hidden input.
     *
     * In JavaScript, you may get the values of [[csrfParam]] and [[csrfToken]] via `yii.getCsrfParam()` and
     * `yii.getCsrfToken()`, respectively. The [[\yii\web\YiiAsset]] asset must be registered.
     * You also need to include CSRF meta tags in your pages by using [[\yii\helpers\Html::csrfMetaTags()]].
     *
     * @see Controller::enableCsrfValidation
     * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
     */
    public $enableCsrfValidation = true;
    /**
     * @var string the name of the token used to prevent CSRF. Defaults to '_csrf'.
     * This property is used only when [[enableCsrfValidation]] is true.
     */
    public $csrfParam = '_csrf';
    /**
     * @var array the configuration for creating the CSRF [[Cookie|cookie]]. This property is used only when
     * both [[enableCsrfValidation]] and [[enableCsrfCookie]] are true.
     */
    public $csrfCookie = ['httpOnly' => true];
    /**
     * @var bool whether to use cookie to persist CSRF token. If false, CSRF token will be stored
     * in session under the name of [[csrfParam]]. Note that while storing CSRF tokens in session increases
     * security, it requires starting a session for every page, which will degrade your site performance.
     */
    public $enableCsrfCookie = true;
    /**
     * @var bool whether cookies should be validated to ensure they are not tampered. Defaults to true.
     */
    public $enableCookieValidation = true;
    /**
     * @var string a secret key used for cookie validation. This property must be set if [[enableCookieValidation]] is true.
     */
    public $cookieValidationKey;
    /**
     * @var string the name of the POST parameter that is used to indicate if a request is a PUT, PATCH or DELETE
     * request tunneled through POST. Defaults to '_method'.
     * @see _getMethod()
     * @see getBodyParams()
     */
    public $methodParam = '_method';
    /**
     * @var array the parsers for converting the raw HTTP request body into [[bodyParams]].
     * The array keys are the request `Content-Types`, and the array values are the
     * corresponding configurations for [[Yii::createObject|creating the parser objects]].
     * A parser must implement the [[RequestParserInterface]].
     *
     * To enable parsing for JSON requests you can use the [[JsonParser]] class like in the following example:
     *
     * ```
     * [
     *     'application/json' => 'yii\web\JsonParser',
     * ]
     * ```
     *
     * To register a parser for parsing all request types you can use `'*'` as the array key.
     * This one will be used as a fallback in case no other types match.
     *
     * @see getBodyParams()
     */
    public $parsers = [];
    /**
     * @var array the configuration for trusted security related headers.
     *
     * An array key is an IPv4 or IPv6 IP address in CIDR notation for matching a client.
     *
     * An array value is a list of headers to trust. These will be matched against
     * [[secureHeaders]] to determine which headers are allowed to be sent by a specified host.
     * The case of the header names must be the same as specified in [[secureHeaders]].
     *
     * For example, to trust all headers listed in [[secureHeaders]] for IP addresses
     * in range `192.168.0.0-192.168.0.254` write the following:
     *
     * ```php
     * [
     *     '192.168.0.0/24',
     * ]
     * ```
     *
     * To trust just the `X-Forwarded-For` header from `10.0.0.1`, use:
     *
     * ```
     * [
     *     '10.0.0.1' => ['X-Forwarded-For']
     * ]
     * ```
     *
     * Default is to trust all headers except those listed in [[secureHeaders]] from all hosts.
     * Matches are tried in order and searching is stopped when IP matches.
     *
     * > Info: Matching is performed using [[IpValidator]].
     * See [[IpValidator::::setRanges()|IpValidator::setRanges()]]
     * and [[IpValidator::networks]] for advanced matching.
     *
     * @see $secureHeaders
     */
    public $trustedHosts = [];
    /**
     * @var array lists of headers that are, by default, subject to the trusted host configuration.
     * These headers will be filtered unless explicitly allowed in [[trustedHosts]].
     * The match of header names is case-insensitive.
     * @see https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
     * @see $trustedHosts
     */
    public $secureHeaders = [
        // Common:
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',

        // Microsoft:
        'Front-End-Https',
        'X-Rewrite-Url',
    ];
    /**
     * @var string[] List of headers where proxies store the real client IP.
     * It's not advisable to put insecure headers here.
     * The match of header names is case-insensitive.
     * @see $trustedHosts
     * @see $secureHeaders
     */
    public $ipHeaders = [
        'X-Forwarded-For', // Common
    ];
    /**
     * @var array list of headers to check for determining whether the connection is made via HTTPS.
     * The array keys are header names and the array value is a list of header values that indicate a secure connection.
     * The match of header names and values is case-insensitive.
     * It's not advisable to put insecure headers here.
     * @see $trustedHosts
     * @see $secureHeaders
     */
    public $secureProtocolHeaders = [
        'X-Forwarded-Proto' => ['https'], // Common
        'Front-End-Https' => ['on'], // Microsoft
    ];


    /**
     * @var CookieCollection Collection of request cookies.
     */
    protected $_cookies;
    /**
     * @var HeaderCollection Collection of request headers.
     */
    protected $_headers;

    protected $_serverParams;

    protected $_contentTypes;
    protected $_languages;
    protected $_port;
    protected $_securePort;
    protected $_csrfToken;
    protected $_url;
    protected $_pathInfo;
    protected $_scriptFile;
    protected $_scriptUrl;
    protected $_baseUrl;
    protected $_hostInfo;
    protected $_hostName;
    protected $_queryParams;
    protected $_bodyParams;
    protected $_rawBody;
    protected $_method;


    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->getCsrfToken();
    }

    /**
     * Returns the header collection.
     * The header collection contains incoming HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection();
            $headers = $this->reactRequest->getHeaders();
            foreach ($headers as $name => $value) {
                $this->_headers->add($name, $value);
            }
            $this->filterHeaders($this->_headers);
        }

        return $this->_headers;
    }

    /**
     * Returns the cookie collection.
     *
     * Through the returned cookie collection, you may access a cookie using the following syntax:
     *
     * ```php
     * $cookie = $request->cookies['name']
     * if ($cookie !== null) {
     *     $value = $cookie->value;
     * }
     *
     * // alternatively
     * $value = $request->cookies->getValue('name');
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection($this->loadCookies(), [
                'readOnly' => true,
            ]);
        }

        return $this->_cookies;
    }

    /**
     * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
     * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
     * The value returned is turned into upper case.
     */
    public function getMethod()
    {
        if (!isset($this->_method)) {
            /** @var string $methodHeader */
            $methodHeader = $this->getHeaders()->get('X-Http-Method-Override');
            $reactMethod = strtoupper($this->reactRequest->getMethod());
            $reactBody = $this->reactRequest->getParsedBody();
            if ($reactMethod === 'POST' && isset($reactBody[$this->methodParam])) {
                $this->_method = strtoupper($reactBody[$this->methodParam]);
            } elseif ($this->getHeaders()->has('X-Http-Method-Override')) {
                $this->_method = $methodHeader;
            } elseif ($reactMethod = $this->reactRequest->getMethod()) {
                $this->_method = $reactMethod;
            } else {
                $this->_method = 'GET';
            }
        }
        return $this->_method;
    }





    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = $this->reactRequest->getBody();
        }

        return $this->_rawBody;
    }

    /**
     * Sets the raw HTTP request body, this method is mainly used by test scripts to simulate raw HTTP requests.
     * @param string $rawBody the request body
     */
    public function setRawBody($rawBody)
    {
        $this->_rawBody = $rawBody;
    }

    /**
     * Returns the request parameters given in the request body.
     *
     * Request parameters are determined using the parsers configured in [[parsers]] property.
     * If no parsers are configured for the current [[contentType]] it uses the PHP function `mb_parse_str()`
     * to parse the [[rawBody|request body]].
     * @return array the request parameters given in the request body.
     * @throws \Reaction\Exceptions\InvalidConfigException if a registered parser does not implement the [[RequestParserInterface]].
     * @see _getMethod()
     * @see getBodyParam()
     * @see setBodyParams()
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            $reactMethod = strtoupper($this->reactRequest->getMethod());
            $reactBody = $this->reactRequest->getParsedBody();
            if ($reactMethod === 'POST' && isset($reactBody[$this->methodParam])) {
                $this->_bodyParams = $reactBody;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }

            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }

            if (isset($this->parsers[$contentType])) {
                $parser = Reaction::create($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the Reaction\\Web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Reaction::create($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException('The fallback request parser is invalid. It must implement the Reaction\\Web\\RequestParserInterface.');
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $reactBody;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }

    /**
     * Sets the request body parameters.
     * @param array $values the request body parameters (name-value pairs)
     * @see getBodyParam()
     * @see getBodyParams()
     */
    public function setBodyParams($values)
    {
        $this->_bodyParams = $values;
    }

    /**
     * Returns the named request body parameter value.
     * If the parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return mixed the parameter value
     * @see getBodyParams()
     * @see setBodyParams()
     */
    public function getBodyParam($name, $defaultValue = null)
    {
        /** @var array|object $params */
        $params = $this->getBodyParams();

        if (is_object($params)) {
            // unable to use `ArrayHelper::getValue()` due to different dots in key logic and lack of exception handling
            try {
                return $params->{$name};
            } catch (\Exception $e) {
                return $defaultValue;
            }
        }

        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    /**
     * Returns POST parameter with a given name. If name isn't specified, returns an array of all POST parameters.
     *
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return array|mixed
     */
    public function post($name = null, $defaultValue = null)
    {
        if ($name === null) {
            return $this->getBodyParams();
        }

        return $this->getBodyParam($name, $defaultValue);
    }

    /**
     * Returns the request parameters given in the [[queryString]].
     *
     * This method will return the contents of `$_GET` if params where not explicitly set.
     * @return array the request GET parameter values.
     * @see setQueryParams()
     */
    public function getQueryParams()
    {
        if (!isset($this->_queryParams)) {
            $this->_queryParams = $this->reactRequest->getQueryParams();
        }
        return $this->_queryParams;
    }

    /**
     * Sets the request [[queryString]] parameters.
     * @param array $values the request query parameters (name-value pairs)
     * @see getQueryParam()
     * @see getQueryParams()
     */
    public function setQueryParams($values)
    {
        $this->_queryParams = $values;
    }

    /**
     * Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
     *
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return array|mixed
     */
    public function get($name = null, $defaultValue = null)
    {
        if ($name === null) {
            return $this->getQueryParams();
        }

        return $this->getQueryParam($name, $defaultValue);
    }

    /**
     * Returns the named GET parameter value.
     * If the GET parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the GET parameter name.
     * @param mixed $defaultValue the default parameter value if the GET parameter does not exist.
     * @return mixed the GET parameter value
     * @see getBodyParam()
     */
    public function getQueryParam($name, $defaultValue = null)
    {
        $params = $this->getQueryParams();

        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    /**
     * Returns the schema and host part of the current request URL.
     *
     * The returned URL does not have an ending slash.
     *
     * By default this value is based on the user request information. This method will
     * return the value of `$_SERVER['HTTP_HOST']` if it is available or `$_SERVER['SERVER_NAME']` if not.
     * You may want to check out the [PHP documentation](http://php.net/manual/en/reserved.variables.server.php)
     * for more information on these variables.
     *
     * You may explicitly specify it by setting the [[setHostInfo()|hostInfo]] property.
     *
     * > Warning: Dependent on the server configuration this information may not be
     * > reliable and [may be faked by the user sending the HTTP request](https://www.acunetix.com/vulnerabilities/web/host-header-attack).
     * > If the webserver is configured to serve the same site independent of the value of
     * > the `Host` header, this value is not reliable. In such situations you should either
     * > fix your webserver configuration or explicitly set the value by setting the [[setHostInfo()|hostInfo]] property.
     * > If you don't have access to the server configuration, you can setup [[\yii\filters\HostControl]] filter at
     * > application level in order to protect against such kind of attack.
     *
     * @property string|null schema and hostname part (with port number if needed) of the request URL
     * (e.g. `http://www.example.com`), null if can't be obtained from `$_SERVER` and wasn't set.
     * See [[getHostInfo()]] for security related notes on this property.
     * @return string|null schema and hostname part (with port number if needed) of the request URL
     * (e.g. `http://www.example.com`), null if can't be obtained from `$_SERVER` and wasn't set.
     * @see setHostInfo()
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $secure = $this->getIsSecureConnection();
            $http = $secure ? 'https' : 'http';
            $serverName = $this->getServerParam('SERVER_NAME');

            if ($this->getHeaders()->has('X-Forwarded-Host')) {
                $this->_hostInfo = $http . '://' . $this->getHeaders()->get('X-Forwarded-Host');
            } elseif ($this->getHeaders()->has('Host')) {
                $this->_hostInfo = $http . '://' . $this->getHeaders()->get('Host');
            } elseif (isset($serverName)) {
                $this->_hostInfo = $http . '://' . $serverName;
                $port = $secure ? $this->getSecurePort() : $this->getPort();
                if (($port !== 80 && !$secure) || ($port !== 443 && $secure)) {
                    $this->_hostInfo .= ':' . $port;
                }
            }
        }

        return $this->_hostInfo;
    }

    /**
     * Sets the schema and host part of the application URL.
     * This setter is provided in case the schema and hostname cannot be determined
     * on certain Web servers.
     * @param string|null $value the schema and host part of the application URL. The trailing slashes will be removed.
     * @see getHostInfo() for security related notes on this property.
     */
    public function setHostInfo($value)
    {
        $this->_hostName = null;
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }

    /**
     * Returns the host part of the current request URL.
     * Value is calculated from current [[getHostInfo()|hostInfo]] property.
     *
     * > Warning: The content of this value may not be reliable, dependent on the server
     * > configuration. Please refer to [[getHostInfo()]] for more information.
     *
     * @return string|null hostname part of the request URL (e.g. `www.yiiframework.com`)
     * @see getHostInfo()
     */
    public function getHostName()
    {
        if ($this->_hostName === null) {
            $this->_hostName = parse_url($this->getHostInfo(), PHP_URL_HOST);
        }

        return $this->_hostName;
    }

    /**
     * Returns the relative URL for the application.
     * This is similar to [[scriptUrl]] except that it does not include the script file name,
     * and the ending slashes are removed.
     * @return string the relative URL for the application
     * @see setScriptUrl()
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            $this->_baseUrl = rtrim(dirname($this->getScriptUrl()), '\\/');
        }

        return $this->_baseUrl;
    }

    /**
     * Sets the relative URL for the application.
     * By default the URL is determined based on the entry script URL.
     * This setter is provided in case you want to change this behavior.
     * @param string $value the relative URL for the application
     */
    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value;
    }

    /**
     * Returns the relative URL of the entry script.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string the relative URL of the entry script.
     * @throws InvalidConfigException if unable to determine the entry script URL
     */
    public function getScriptUrl()
    {
        $server = $this->getServerParams();
        if ($this->_scriptUrl === null) {
            $scriptFile = $this->getScriptFile();
            $scriptName = basename($scriptFile);
            if (isset($server['SCRIPT_NAME']) && basename($server['SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $server['SCRIPT_NAME'];
            } elseif (isset($server['PHP_SELF']) && basename($server['PHP_SELF']) === $scriptName) {
                $this->_scriptUrl = $server['PHP_SELF'];
            } elseif (isset($server['ORIG_SCRIPT_NAME']) && basename($server['ORIG_SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $server['ORIG_SCRIPT_NAME'];
            } elseif (isset($server['PHP_SELF']) && ($pos = strpos($server['PHP_SELF'], '/' . $scriptName)) !== false) {
                $this->_scriptUrl = substr($server['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
            } elseif (!empty($server['DOCUMENT_ROOT']) && strpos($scriptFile, $server['DOCUMENT_ROOT']) === 0) {
                $this->_scriptUrl = str_replace([$server['DOCUMENT_ROOT'], '\\'], ['', '/'], $scriptFile);
            } else {
                throw new InvalidConfigException('Unable to determine the entry script URL.');
            }
        }

        return $this->_scriptUrl;
    }

    /**
     * Sets the relative URL for the application entry script.
     * This setter is provided in case the entry script URL cannot be determined
     * on certain Web servers.
     * @param string|null $value the relative URL for the application entry script.
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value === null ? null : '/' . trim($value, '/');
    }

    /**
     * Returns the entry script file path.
     * The default implementation will simply return `$_SERVER['SCRIPT_FILENAME']`.
     * @return string the entry script file path
     * @throws InvalidConfigException
     */
    public function getScriptFile()
    {
        if (isset($this->_scriptFile)) {
            return $this->_scriptFile;
        }
        $scriptFilename = $this->getServerParam('SCRIPT_FILENAME');
        if (isset($scriptFilename)) {
            return $scriptFilename;
        }

        throw new InvalidConfigException('Unable to determine the entry script file path.');
    }

    /**
     * Sets the entry script file path.
     * The entry script file path normally can be obtained from `$_SERVER['SCRIPT_FILENAME']`.
     * If your server configuration does not return the correct value, you may configure
     * this property to make it right.
     * @param string $value the entry script file path.
     */
    public function setScriptFile($value)
    {
        $this->_scriptFile = $value;
    }

    /**
     * Returns the path info of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting and ending slashes are both removed.
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is already URL-decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    public function getPathInfo()
    {
        if ($this->_pathInfo === null) {
            $this->_pathInfo = $this->resolvePathInfo();
        }

        return $this->_pathInfo;
    }

    /**
     * Sets the path info of the current request.
     * This method is mainly provided for testing purpose.
     * @param string|null $value the path info of the current request
     */
    public function setPathInfo($value)
    {
        $this->_pathInfo = $value === null ? null : ltrim($value, '/');
    }

    /**
     * Returns the currently requested absolute URL.
     * This is a shortcut to the concatenation of [[hostInfo]] and [[url]].
     * @return string the currently requested absolute URL.
     */
    public function getAbsoluteUrl()
    {
        return $this->getHostInfo() . rtrim($this->getUrl(), '/');
    }

    /**
     * Returns the currently requested relative URL.
     * This refers to the portion of the URL that is after the [[hostInfo]] part.
     * It includes the [[queryString]] part if any.
     * @return string the currently requested relative URL. Note that the URI returned may be URL-encoded depending on the client.
     * @throws InvalidConfigException if the URL cannot be determined due to unusual server configuration
     */
    public function getUrl()
    {
        if ($this->_url === null) {
            $this->_url = $this->resolveRequestUri();
        }

        return $this->_url;
    }

    /**
     * Sets the currently requested relative URL.
     * The URI must refer to the portion that is after [[hostInfo]].
     * Note that the URI should be URL-encoded.
     * @param string $value the request URI to be set
     */
    public function setUrl($value)
    {
        $this->_url = $value;
    }

    /**
     * Returns part of the request URL that is after the question mark.
     * @return string part of the request URL that is after the question mark
     */
    public function getQueryString()
    {
        $query = $this->reactRequest->getUri()->getQuery();
        return isset($query) ? $query : '';
    }



    /**
     * Get server parameters
     * @return array
     */
    public function getServerParams() {
        if (!isset($this->_serverParams)) {
            $this->_serverParams = Reaction\Helpers\ArrayHelper::merge($this->getDefaultServerParams(), $this->reactRequest->getServerParams());
        }
        return $this->_serverParams;
    }

    /**
     * Set server parameters
     * @param array|null $params
     */
    public function setServerParams($params = null) {
        $this->_serverParams = $params;
    }

    /**
     * Return if the request is sent via secure channel (https).
     * @return bool if the request is sent via secure channel (https)
     */
    public function getIsSecureConnection()
    {
        $sHttps = $this->getServerParam('HTTPS');
        if (isset($sHttps) && (strcasecmp($sHttps, 'on') === 0 || $sHttps == 1)) {
            return true;
        }
        foreach ($this->secureProtocolHeaders as $header => $values) {
            if (($headerValue = $this->getHeaders()->get($header, null)) !== null) {
                foreach ($values as $value) {
                    if (is_string($headerValue) && strcasecmp($headerValue, $value) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Returns the server name.
     * @return string server name, null if not available
     */
    public function getServerName()
    {
        return $this->getServerParam('SERVER_NAME', null);
    }

    /**
     * Returns the server port number.
     * @return int|null server port number, null if not available
     */
    public function getServerPort()
    {
        return $this->getServerParam('SERVER_PORT', null);
    }

    /**
     * Returns the URL referrer.
     * @return string|null URL referrer, null if not available
     */
    public function getReferrer()
    {
        return $this->getHeaders()->get('Referer');
    }

    /**
     * Returns the URL origin of a CORS request.
     *
     * The return value is taken from the `Origin` [[getHeaders()|header]] sent by the browser.
     *
     * Note that the origin request header indicates where a fetch originates from.
     * It doesn't include any path information, but only the server name.
     * It is sent with a CORS requests, as well as with POST requests.
     * It is similar to the referer header, but, unlike this header, it doesn't disclose the whole path.
     * Please refer to <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Origin> for more information.
     *
     * @return string|null URL origin of a CORS request, `null` if not available.
     * @see _getHeaders()
     */
    public function getOrigin()
    {
        /** @var string|null $origin */
        $origin = $this->getHeaders()->get('origin');
        return $origin;
    }

    /**
     * Returns the user agent.
     * @return string|null user agent, null if not available
     */
    public function getUserAgent()
    {
        return $this->getHeaders()->get('User-Agent');
    }

    /**
     * Returns the user IP address.
     * The IP is determined using headers and / or `$_SERVER` variables.
     * @return string|null user IP address, null if not available
     */
    public function getUserIP()
    {
        foreach ($this->ipHeaders as $ipHeader) {
            if ($this->getHeaders()->has($ipHeader)) {
                return trim(explode(',', $this->getHeaders()->get($ipHeader))[0]);
            }
        }

        return $this->getRemoteIP();
    }

    /**
     * Returns the user host name.
     * The HOST is determined using headers and / or `$_SERVER` variables.
     * @return string|null user host name, null if not available
     */
    public function getUserHost()
    {
        foreach ($this->ipHeaders as $ipHeader) {
            if ($this->getHeaders()->has($ipHeader)) {
                return gethostbyaddr(trim(explode(',', $this->getHeaders()->get($ipHeader))[0]));
            }
        }

        return $this->getRemoteHost();
    }

    /**
     * Returns the IP on the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote IP address, `null` if not available.
     */
    public function getRemoteIP()
    {
        return $this->getServerParam('REMOTE_ADDR', null);
    }

    /**
     * Returns the host name of the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote host name, `null` if not available
     * @see getUserHost()
     * @see getRemoteIP()
     */
    public function getRemoteHost()
    {
        return $this->getServerParam('REMOTE_HOST', null);
    }

    /**
     * Returns the port to use for insecure requests.
     * Defaults to 80, or the port specified by the server if the current
     * request is insecure.
     * @return int port number for insecure requests.
     * @see setPort()
     */
    public function getPort()
    {
        if ($this->_port === null) {
            $serverPort = $this->getServerPort();
            $this->_port = !$this->getIsSecureConnection() && $serverPort !== null ? $serverPort : 80;
        }

        return $this->_port;
    }

    /**
     * Sets the port to use for insecure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param int $value port number.
     */
    public function setPort($value)
    {
        if ($value != $this->_port) {
            $this->_port = (int)$value;
            $this->_hostInfo = null;
        }
    }



    /**
     * Returns the port to use for secure requests.
     * Defaults to 443, or the port specified by the server if the current
     * request is secure.
     * @return int port number for secure requests.
     * @see setSecurePort()
     */
    public function getSecurePort()
    {
        if ($this->_securePort === null) {
            $serverPort = $this->getServerPort();
            $this->_securePort = $this->getIsSecureConnection() && $serverPort !== null ? $serverPort : 443;
        }

        return $this->_securePort;
    }

    /**
     * Sets the port to use for secure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param int $value port number.
     */
    public function setSecurePort($value)
    {
        if ($value != $this->_securePort) {
            $this->_securePort = (int)$value;
            $this->_hostInfo = null;
        }
    }


    /**
     * Returns the content types acceptable by the end user.
     *
     * This is determined by the `Accept` HTTP header. For example,
     *
     * ```php
     * $_SERVER['HTTP_ACCEPT'] = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $types = $request->getAcceptableContentTypes();
     * print_r($types);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @return array the content types ordered by the quality score. Types with the highest scores
     * will be returned first. The array keys are the content types, while the array values
     * are the corresponding quality score and other parameters as given in the header.
     */
    public function getAcceptableContentTypes()
    {
        if ($this->_contentTypes === null) {
            if ($this->getHeaders()->get('Accept') !== null) {
                $this->_contentTypes = $this->parseAcceptHeader($this->getHeaders()->get('Accept'));
            } else {
                $this->_contentTypes = [];
            }
        }

        return $this->_contentTypes;
    }

    /**
     * Sets the acceptable content types.
     * Please refer to [[getAcceptableContentTypes()]] on the format of the parameter.
     * @param array $value the content types that are acceptable by the end user. They should
     * be ordered by the preference level.
     * @see getAcceptableContentTypes()
     * @see parseAcceptHeader()
     */
    public function setAcceptableContentTypes($value)
    {
        $this->_contentTypes = $value;
    }

    /**
     * Returns request content-type
     * The Content-Type header field indicates the MIME type of the data
     * contained in [[getRawBody()]] or, in the case of the HEAD method, the
     * media type that would have been sent had the request been a GET.
     * For the MIME-types the user expects in response, see [[acceptableContentTypes]].
     * @return string request content-type. Null is returned if this information is not available.
     * @link https://tools.ietf.org/html/rfc2616#section-14.17
     * HTTP 1.1 header field definitions
     */
    public function getContentType()
    {
        //fix bug https://bugs.php.net/bug.php?id=66606
        return $this->getHeaders()->get('Content-Type');
    }


    /**
     * Returns the languages acceptable by the end user.
     * This is determined by the `Accept-Language` HTTP header.
     * @return array the languages ordered by the preference level. The first element
     * represents the most preferred language.
     */
    public function getAcceptableLanguages()
    {
        if ($this->_languages === null) {
            if ($this->getHeaders()->has('Accept-Language')) {
                /** @var string $acceptLangHeader */
                $acceptLangHeader = $this->getHeaders()->get('Accept-Language');
                $this->_languages = array_keys($this->parseAcceptHeader($acceptLangHeader));
            } else {
                $this->_languages = [];
            }
        }

        return $this->_languages;
    }

    /**
     * @param array $value the languages that are acceptable by the end user. They should
     * be ordered by the preference level.
     */
    public function setAcceptableLanguages($value)
    {
        $this->_languages = $value;
    }

    /**
     * Parses the given `Accept` (or `Accept-Language`) header.
     *
     * This method will return the acceptable values with their quality scores and the corresponding parameters
     * as specified in the given `Accept` header. The array keys of the return value are the acceptable values,
     * while the array values consisting of the corresponding quality scores and parameters. The acceptable
     * values with the highest quality scores will be returned first. For example,
     *
     * ```php
     * $header = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $accepts = $request->parseAcceptHeader($header);
     * print_r($accepts);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @param string $header the header to be parsed
     * @return array the acceptable values ordered by their quality score. The values with the highest scores
     * will be returned first.
     */
    public function parseAcceptHeader($header)
    {
        $accepts = [];
        foreach (explode(',', $header) as $i => $part) {
            $params = preg_split('/\s*;\s*/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($params)) {
                continue;
            }
            $values = [
                'q' => [$i, array_shift($params), 1],
            ];
            foreach ($params as $param) {
                if (strpos($param, '=') !== false) {
                    list($key, $value) = explode('=', $param, 2);
                    if ($key === 'q') {
                        $values['q'][2] = (float)$value;
                    } else {
                        $values[$key] = $value;
                    }
                } else {
                    $values[] = $param;
                }
            }
            $accepts[] = $values;
        }

        usort($accepts, function($a, $b) {
            $a = $a['q']; // index, name, q
            $b = $b['q'];
            if ($a[2] > $b[2]) {
                return -1;
            }

            if ($a[2] < $b[2]) {
                return 1;
            }

            if ($a[1] === $b[1]) {
                return $a[0] > $b[0] ? 1 : -1;
            }

            if ($a[1] === '*/*') {
                return 1;
            }

            if ($b[1] === '*/*') {
                return -1;
            }

            $wa = $a[1][strlen($a[1]) - 1] === '*';
            $wb = $b[1][strlen($b[1]) - 1] === '*';
            if ($wa xor $wb) {
                return $wa ? 1 : -1;
            }

            return $a[0] > $b[0] ? 1 : -1;
        });

        $result = [];
        foreach ($accepts as $accept) {
            $name = $accept['q'][1];
            $accept['q'] = $accept['q'][2];
            $result[$name] = $accept;
        }

        return $result;
    }

    /**
     * Returns the user-preferred language that should be used by this application.
     * The language resolution is based on the user preferred languages and the languages
     * supported by the application. The method will try to find the best match.
     * @param array $languages a list of the languages supported by the application. If this is empty, the current
     * application language will be returned without further processing.
     * @return string the language that the application should use.
     */
    public function getPreferredLanguage(array $languages = [])
    {
        if (empty($languages)) {
            return Reaction::$app->language;
        }
        foreach ($this->getAcceptableLanguages() as $acceptableLanguage) {
            $acceptableLanguage = str_replace('_', '-', strtolower($acceptableLanguage));
            foreach ($languages as $language) {
                $normalizedLanguage = str_replace('_', '-', strtolower($language));

                if (
                    $normalizedLanguage === $acceptableLanguage // en-us==en-us
                    || strpos($acceptableLanguage, $normalizedLanguage . '-') === 0 // en==en-us
                    || strpos($normalizedLanguage, $acceptableLanguage . '-') === 0 // en-us==en
                ) {
                    return $language;
                }
            }
        }

        return reset($languages);
    }

    /**
     * Gets the Etags.
     *
     * @return array The entity tags
     */
    public function getETags()
    {
        if ($this->getHeaders()->has('If-None-Match')) {
            /** @var string $ifNoneHeader */
            $ifNoneHeader = $this->getHeaders()->get('If-None-Match');
            $eTags = preg_split('/[\s,]+/', str_replace('-gzip', '', $ifNoneHeader), -1, PREG_SPLIT_NO_EMPTY);
            return is_array($eTags) ? $eTags : [];
        }

        return [];
    }

    /**
     * Get server parameter like from '$_SERVER'
     * @param string $name
     * @param mixed  $defaultValue
     * @return mixed
     */
    public function getServerParam($name, $defaultValue = null) {
        return \Reaction\Helpers\ArrayHelper::getValue($this->getServerParams(), $name, $defaultValue);
    }

    /**
     * Set server parameter like from '$_SERVER'
     * @param string $name
     * @param mixed  $value
     */
    public function setServerParam($name, $value = null) {
        $this->getServerParams();
        if ($value === null) {
            Reaction\Helpers\ArrayHelper::remove($this->_serverParams, $name);
            unset($this->_serverParams[$name]);
        } else {
            Reaction\Helpers\ArrayHelper::setValue($this->_serverParams, $name, $value);
        }
    }



    /**
     * Returns the token used to perform CSRF validation.
     *
     * This token is generated in a way to prevent [BREACH attacks](http://breachattack.com/). It may be passed
     * along via a hidden field of an HTML form or an HTTP header value to support CSRF validation.
     * @param bool $regenerate whether to regenerate CSRF token. When this parameter is true, each time
     * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
     * @return string the token used to perform CSRF validation.
     */
    public function getCsrfToken($regenerate = false)
    {
        if ($this->_csrfToken === null || $regenerate) {
            $token = $this->loadCsrfToken();
            if ($regenerate || empty($token)) {
                $token = $this->generateCsrfToken();
            }
            $this->_csrfToken = Reaction::$app->security->maskToken($token);
        }

        return $this->_csrfToken;
    }

    /**
     * @return string the CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned if no such header is sent.
     */
    public function getCsrfTokenFromHeader()
    {
        return $this->getHeaders()->get(static::CSRF_HEADER);
    }

    /**
     * Performs the CSRF validation.
     *
     * This method will validate the user-provided CSRF token by comparing it with the one stored in cookie or session.
     * This method is mainly called in [[Controller::beforeAction()]].
     *
     * Note that the method will NOT perform CSRF validation if [[enableCsrfValidation]] is false or the HTTP method
     * is among GET, HEAD or OPTIONS.
     *
     * @param string $clientSuppliedToken the user-provided CSRF token to be validated. If null, the token will be retrieved from
     * the [[csrfParam]] POST field or HTTP header.
     * @return bool whether CSRF token is valid. If [[enableCsrfValidation]] is false, this method will return true.
     */
    public function validateCsrfToken($clientSuppliedToken = null)
    {
        $method = $this->method;
        // only validate CSRF token on non-"safe" methods https://tools.ietf.org/html/rfc2616#section-9.1.1
        if (!$this->enableCsrfValidation || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $trueToken = $this->getCsrfToken();

        if ($clientSuppliedToken !== null) {
            return $this->validateCsrfTokenInternal($clientSuppliedToken, $trueToken);
        }

        return $this->validateCsrfTokenInternal($this->getBodyParam($this->csrfParam), $trueToken)
            || $this->validateCsrfTokenInternal($this->getCsrfTokenFromHeader(), $trueToken);
    }


    /**
     * Creates instance of [[IpValidator]].
     * You can override this method to adjust validator or implement different matching strategy.
     *
     * @return IpValidator
     */
    protected function getIpValidator()
    {
        return new IpValidator();
    }

    /**
     * Filters headers according to the [[trustedHosts]].
     * @param HeaderCollection $headerCollection
     */
    protected function filterHeaders(HeaderCollection $headerCollection)
    {
        // do not trust any of the [[secureHeaders]] by default
        $trustedHeaders = [];

        // check if the client is a trusted host
        if (!empty($this->trustedHosts)) {
            $validator = $this->getIpValidator();
            $ip = $this->getRemoteIP();
            foreach ($this->trustedHosts as $cidr => $headers) {
                if (!is_array($headers)) {
                    $cidr = $headers;
                    $headers = $this->secureHeaders;
                }
                $validator->setRanges($cidr);
                if ($validator->validate($ip)) {
                    $trustedHeaders = $headers;
                    break;
                }
            }
        }

        // filter all secure headers unless they are trusted
        foreach ($this->secureHeaders as $secureHeader) {
            if (!in_array($secureHeader, $trustedHeaders)) {
                $headerCollection->remove($secureHeader);
            }
        }
    }

    /**
     * Resolves the path info part of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting slashes are both removed (ending slashes will be kept).
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    protected function resolvePathInfo()
    {
        $pathInfo = $this->getUrl();

        if (($pos = strpos($pathInfo, '?')) !== false) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }

        $pathInfo = urldecode($pathInfo);

        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }

        $scriptUrl = $this->getScriptUrl();
        $baseUrl = $this->getBaseUrl();
        $server = $this->getServerParams();
        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($server['PHP_SELF']) && strpos($server['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($server['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new InvalidConfigException('Unable to determine the path info of the current request.');
        }

        if (strlen($pathInfo) === 0 || substr($pathInfo, 0, 1) !== '/') {
            $pathInfo = '/' . $pathInfo;
        }

        return (string)$pathInfo;
    }

    /**
     * Resolves the request URI portion for the currently requested URL.
     * This refers to the portion that is after the [[hostInfo]] part. It includes the [[queryString]] part if any.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string|bool the request URI portion for the currently requested URL.
     * Note that the URI returned may be URL-encoded depending on the client.
     * @throws InvalidConfigException if the request URI cannot be determined due to unusual server configuration
     */
    protected function resolveRequestUri()
    {
        $reactPath = $this->reactRequest->getUri()->getPath();
        $reactQuery = $this->getQueryString();
        $reactPath = $reactQuery !== "" ? $reactPath . '?' . $reactQuery : $reactPath;
        if ($this->getHeaders()->has('X-Rewrite-Url')) { // IIS
            $requestUri = $this->getHeaders()->get('X-Rewrite-Url');
        } elseif (isset($reactPath)) {
            $requestUri = $reactPath;
            if ($requestUri !== '' && $requestUri[0] !== '/') {
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        } else {
            throw new InvalidConfigException('Unable to determine the request URI.');
        }

        return $requestUri;
    }




    /**
     * Converts `$_COOKIE` into an array of [[Cookie]].
     * @return array the cookies obtained from request
     */
    protected function loadCookies()
    {
        $cookies = [];
        $cookiesSource = $this->reactRequest->getCookieParams();
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($cookiesSource as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $data = Reaction::$app->security->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }
                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = Reaction::create([
                        'class' => 'Reaction\Web\Cookie',
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            foreach ($cookiesSource as $name => $value) {
                $cookies[$name] = Reaction::create([
                    'class' => 'Reaction\Web\Cookie',
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    /**
     * Validates CSRF token.
     *
     * @param string|null $clientSuppliedToken The masked client-supplied token.
     * @param string $trueToken The masked true token.
     * @return bool
     */
    protected function validateCsrfTokenInternal($clientSuppliedToken, $trueToken)
    {
        if (!is_string($clientSuppliedToken)) {
            return false;
        }

        $security = Reaction::$app->security;

        return $security->unmaskToken($clientSuppliedToken) === $security->unmaskToken($trueToken);
    }

    /**
     * Loads the CSRF token from cookie or session.
     * @return string the CSRF token loaded from cookie or session. Null is returned if the cookie or session
     * does not have CSRF token.
     */
    protected function loadCsrfToken()
    {
        if ($this->enableCsrfCookie) {
            return $this->getCookies()->getValue($this->csrfParam);
        }

        //TODO: Rewrite
        return $this->app->session->get($this->csrfParam);
    }

    /**
     * Generates an unmasked random token used to perform CSRF validation.
     * @return string the random token for CSRF validation.
     */
    protected function generateCsrfToken()
    {
        $token = Reaction::$app->security->generateRandomString();
        if ($this->enableCsrfCookie) {
            $cookie = $this->createCsrfCookie($token);
            $this->app->response->getCookies()->add($cookie);
        } else {
            $this->app->session->set($this->csrfParam, $token);
        }

        return $token;
    }

    /**
     * Creates a cookie with a randomly generated CSRF token.
     * Initial values specified in [[csrfCookie]] will be applied to the generated cookie.
     * @param string $token the CSRF token
     * @return Cookie the generated cookie
     * @see enableCsrfValidation
     */
    protected function createCsrfCookie($token)
    {
        $options = $this->csrfCookie;
        return Reaction::create(array_merge($options, [
            'class' => 'Reaction\Web\Cookie',
            'name' => $this->csrfParam,
            'value' => $token,
        ]));
    }

    /**
     * Get default server parameters
     * @return array
     */
    protected function getDefaultServerParams() {
        $params = [
            'DOCUMENT_ROOT' => getcwd(),
            'SCRIPT_FILENAME' => getcwd() . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_FILENAME'],
        ];

        return $params;
    }

    /**
     * Get React PHP request
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function getReactRequest() {
        return $this->app->request;
    }
}