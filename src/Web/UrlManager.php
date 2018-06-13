<?php

namespace Reaction\Web;

use Reaction;
use Reaction\Base\RequestAppComponent;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\Url;

/**
 * UrlManager handles HTTP request parsing and creation of URLs based on a set of rules.
 *
 * UrlManager is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->urlManager`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 *
 * ```php
 * 'urlManager' => [
 *     'enablePrettyUrl' => true,
 *     'rules' => [
 *         // your rules go here
 *     ],
 *     // ...
 * ]
 * ```
 *
 * Rules are classes implementing the [[UrlRuleInterface]], by default that is [[UrlRule]].
 * For nesting rules, there is also a [[GroupUrlRule]] class.
 *
 * For more details and usage information on UrlManager, see the [guide article on routing](guide:runtime-routing).
 *
 * @property string $baseUrl The base URL that is used by [[createUrl()]] to prepend to created URLs.
 * @property string $hostInfo The host info (e.g. `http://www.example.com`) that is used by
 * [[createAbsoluteUrl()]] to prepend to created URLs.
 * @property string $scriptUrl The entry script URL that is used by [[createUrl()]] to prepend to created
 * URLs.
 */
class UrlManager extends RequestAppComponent implements Reaction\Base\ComponentAutoloadInterface
{
    /**
     * @var array the rules for creating and parsing URLs when [[enablePrettyUrl]] is `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`. Each element in the array
     * is the configuration array for creating a single URL rule. The configuration will
     * be merged with [[ruleConfig]] first before it is used for creating the rule object.
     *
     * A special shortcut format can be used if a rule only specifies [[UrlRule::pattern|pattern]]
     * and [[UrlRule::route|route]]: `'pattern' => 'route'`. That is, instead of using a configuration
     * array, one can use the key to represent the pattern and the value the corresponding route.
     * For example, `'post/<id:\d+>' => 'post/view'`.
     *
     * For RESTful routing the mentioned shortcut format also allows you to specify the
     * [[UrlRule::verb|HTTP verb]] that the rule should apply for.
     * You can do that  by prepending it to the pattern, separated by space.
     * For example, `'PUT post/<id:\d+>' => 'post/update'`.
     * You may specify multiple verbs by separating them with comma
     * like this: `'POST,PUT post/index' => 'post/create'`.
     * The supported verbs in the shortcut format are: GET, HEAD, POST, PUT, PATCH and DELETE.
     * Note that [[UrlRule::mode|mode]] will be set to PARSING_ONLY when specifying verb in this way
     * so you normally would not specify a verb for normal GET request.
     *
     * Here is an example configuration for RESTful CRUD controller:
     *
     * ```php
     * [
     *     'dashboard' => 'site/index',
     *
     *     'POST <controller:[\w-]+>s' => '<controller>/create',
     *     '<controller:[\w-]+>s' => '<controller>/index',
     *
     *     'PUT <controller:[\w-]+>/<id:\d+>'    => '<controller>/update',
     *     'DELETE <controller:[\w-]+>/<id:\d+>' => '<controller>/delete',
     *     '<controller:[\w-]+>/<id:\d+>'        => '<controller>/view',
     * ];
     * ```
     *
     * Note that if you modify this property after the UrlManager object is created, make sure
     * you populate the array with rule objects instead of rule configurations.
     */
    public $rules = [];
    /**
     * @var string the URL suffix used when [[enablePrettyUrl]] is `true`.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $suffix;
    /**
     * @var bool whether to show entry script name in the constructed URL. Defaults to `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $showScriptName = true;
    /**
     * @var string the GET parameter name for route. This property is used only if [[enablePrettyUrl]] is `false`.
     */
    public $routeParam = 'r';
    /**
     * @var CacheInterface|string the cache object or the application component ID of the cache object.
     * Compiled URL rules will be cached through this cache object, if it is available.
     *
     * After the UrlManager object is created, if you want to change this property,
     * you should only assign it with a cache object.
     * Set this property to `false` if you do not want to cache the URL rules.
     *
     * Cache entries are stored for the time set by [[\yii\caching\Cache::$defaultDuration|$defaultDuration]] in
     * the cache configuration, which is unlimited by default. You may want to tune this value if your [[rules]]
     * change frequently.
     */
    public $cache = 'cache';
    /**
     * @var array the default configuration of URL rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $ruleConfig = ['class' => 'yii\web\UrlRule'];

    /**
     * @var string the cache key for cached rules
     */
    protected $cacheKey = __CLASS__;

    private $_baseUrl;
    private $_scriptUrl;
    private $_hostInfo;
    private $_homeUrl;


    /**
     * Initializes UrlManager.
     */
    public function init()
    {
        parent::init();
        $this->processRequest();
    }

    /**
     * Process current request
     */
    protected function processRequest()
    {
        if (!Reaction::$app->getI18n()->detectLanguageByUrl) {
            return;
        }
        $langPrefixes = Reaction::$app->getI18n()->languagePrefixes;
        $rawUrl = trim($this->app->reqHelper->getPathInfo(), '/');
        $requestPrefix = '';
        $rawUrlParts = explode('/', $rawUrl);
        if (!empty($rawUrlParts) && isset($langPrefixes[$rawUrlParts[0]])) {
            $requestPrefix = $rawUrlParts[0];
            $this->baseUrl = $requestPrefix;
            array_shift($rawUrlParts);
        }
        $rawUrl = '/' . trim(implode('/', $rawUrlParts), '/');
        $requestLanguage = $langPrefixes[$requestPrefix];
        $this->app->reqHelper->setPathInfo($rawUrl);
        $this->app->language = $requestLanguage;
    }

    /**
     * Creates a URL using the given route and query parameters.
     *
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```php
     * // generates: /index.php?r=site%2Findex&param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     *
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```php
     * // generates: /index.php?r=site%2Findex&param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * The URL created is a relative one. Use [[createAbsoluteUrl()]] to create an absolute URL.
     *
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @return string the created URL
     */
    public function createUrl($params)
    {
        $params = (array) $params;
        $anchor = isset($params['#']) ? '#'.$params['#'] : '';
        if (isset($params['#'])) {
            unset($params['#']);
        }

        $route = trim($params[0], '/');
        unset($params[0]);

        $baseUrl = $this->getBaseUrl();

        $cacheKey = $route.'?';
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $cacheKey .= $key.'&';
            }
        }

        if ($this->suffix !== null) {
            $route .= $this->suffix;
        }
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $route .= '?'.$query;
        }

        $route = ltrim($route, '/');
        $url = "{$baseUrl}/{$route}";
        $url = $url !== "/" ? rtrim($url, '/') : $url;

        return "{$url}{$anchor}";
    }

    /**
     * Creates an absolute URL using the given route and query parameters.
     *
     * This method prepends the URL created by [[createUrl()]] with the [[hostInfo]].
     *
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param string|null  $scheme the scheme to use for the URL (either `http`, `https` or empty string
     * for protocol-relative URL).
     * If not specified the scheme of the current request will be used.
     * @return string the created URL
     * @see createUrl()
     * @throws InvalidConfigException
     */
    public function createAbsoluteUrl($params, $scheme = null)
    {
        $params = (array) $params;
        $url = $this->createUrl($params);
        if (strpos($url, '://') === false) {
            $hostInfo = $this->getHostInfo();
            if (strncmp($url, '//', 2) === 0) {
                $url = substr($hostInfo, 0, strpos($hostInfo, '://')).':'.$url;
            } else {
                $url = $hostInfo.$url;
            }
        }

        return Url::ensureScheme($url, $scheme);
    }

    /**
     * Returns the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * It defaults to [[Request::baseUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @return string the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[baseUrl]] is not configured.
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            $request = $this->app->reqHelper;
            if ($request instanceof RequestHelper) {
                $this->_baseUrl = $request->getBaseUrl();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::baseUrl correctly as you are running a console application.');
            }
        }

        return $this->_baseUrl;
    }

    /**
     * Sets the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @param string $value the base URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value === null ? null : '/' . trim(Reaction::$app->getAlias($value), '/');
    }

    /**
     * Returns the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * It defaults to [[Request::scriptUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @return string the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[scriptUrl]] is not configured.
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            if ($this->app->reqHelper instanceof RequestHelper) {
                $this->_scriptUrl = $this->app->reqHelper->getScriptUrl();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::scriptUrl correctly as you are running a console application.');
            }
        }

        return $this->_scriptUrl;
    }

    /**
     * Sets the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @param string $value the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value;
    }

    /**
     * Returns the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @return string the host info (e.g. `http://www.example.com`) that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[hostInfo]] is not configured.
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            if ($this->app->reqHelper instanceof RequestHelper) {
                $this->_hostInfo = $this->app->reqHelper->getHostInfo();
            } else {
                throw new InvalidConfigException('Please configure UrlManager::hostInfo correctly as you are running a console application.');
            }
        }

        return $this->_hostInfo;
    }

    /**
     * Sets the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @param string $value the host info (e.g. "http://www.example.com") that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     */
    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }

    /**
     * Getter for $homeUrl
     * @return string
     */
    public function getHomeUrl() {
        return $this->_homeUrl !== null ? $this->_homeUrl : Reaction::$app->urlManager->getHomeUrl();
    }

    /**
     * Setter for $homeUrl
     * @param string $homeUrl
     */
    public function setHomeUrl($homeUrl) {
        $this->_homeUrl = $homeUrl;
    }
}
