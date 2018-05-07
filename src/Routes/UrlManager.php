<?php

namespace Reaction\Routes;

use Reaction\Base\Component;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\Url;
use Reaction\Web\AppRequestInterface;

/**
 * Class UrlManager
 * @package Reaction\Routes
 */
class UrlManager extends Component implements UrlManagerInterface
{    /**
     * @var string the URL suffix used.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     */
    public $suffix;

    /** @var string Application default base URL */
    protected $_baseUrl;
    /** @var string Application host info. (http://www.example.com) */
    protected $_hostInfo;
    /** @var string Application home URL */
    protected $_homeUrl;

    /**
     * Creates a URL using the given route and query parameters.
     *
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```php
     * // generates: /site/index?param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     *
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```php
     * // generates: /site/index?param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * If you want to create a URL and such route is defined in any controller, for example '/site/view/{userName:\w+}',
     *
     * ```php
     * // generates: /site/view/admin
     * ['site/view', 'userName' => 'admin']
     *
     * // generates: /site/view/admin?param1=value
     * ['site/view', 'userName' => 'admin', 'param1' => 'value1']
     * ```
     *
     * The URL created is a relative one. Use [[createAbsoluteUrl()]] to create an absolute URL.
     *
     * Note that unlike [[\Reaction\Helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array             $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param AppRequestInterface|null $request
     * @return string the created URL
     * @throws InvalidConfigException
     */
    public function createUrl($params, $request = null)
    {
        $params = (array) $params;
        $anchor = isset($params['#']) ? '#' . $params['#'] : '';
        unset($params['#']);

        $route = rtrim($params[0], '/');
        unset($params[0]);
        $route = $this->buildRoutePath($route, $params);

        $baseUrl = $this->getBaseUrl($request);

        if ($this->suffix !== null) {
            $route .= $this->suffix;
        }
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $route .= '?' . $query;
        }

        $route = ltrim($route, '/');
        return "$baseUrl/{$route}{$anchor}";
    }

    /**
     * Creates an absolute URL using the given route and query parameters.
     *
     * This method prepends the URL created by [[createUrl()]] with the [[hostInfo]].
     *
     * Note that unlike [[\Reaction\Helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param string|null  $scheme the scheme to use for the URL (either `http`, `https` or empty string
     * for protocol-relative URL).
     * If not specified the scheme of the current request will be used.
     * @param AppRequestInterface|null $request
     * @return string the created URL
     * @see createUrl()
     * @throws InvalidConfigException
     */
    public function createAbsoluteUrl($params, $scheme = null, $request = null)
    {
        $params = (array) $params;
        $url = $this->createUrl($params);
        if (strpos($url, '://') === false) {
            $hostInfo = $this->getHostInfo($request);
            if (strncmp($url, '//', 2) === 0) {
                $url = substr($hostInfo, 0, strpos($hostInfo, '://')) . ':' . $url;
            } else {
                $url = $hostInfo . $url;
            }
        }

        return Url::ensureScheme($url, $scheme);
    }

    /**
     * Getter for $baseUrl
     * @param AppRequestInterface|null $request
     * @return string
     * @throws InvalidConfigException
     */
    public function getBaseUrl($request = null)
    {
        if ($request !== null && $request instanceof AppRequestInterface) {
            return $request->getBaseUrl();
        }
        if ($this->_baseUrl === null) {
            throw new InvalidConfigException('Please configure UrlManager::baseUrl correctly if you are running a console application.');
        }
        return $this->_baseUrl;
    }

    /**
     * Setter for $baseUrl
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->_baseUrl = $baseUrl;
    }

    /**
     * Getter for $hostInfo
     * @param AppRequestInterface|null $request
     * @return string
     * @throws InvalidConfigException
     */
    public function getHostInfo($request = null)
    {
        if ($request !== null && $request instanceof AppRequestInterface) {
            return $request->getHostInfo();
        }
        if ($this->_hostInfo === null) {
            throw new InvalidConfigException('Please configure UrlManager::hostInfo correctly if you are running a console application.');
        }
        return $this->_hostInfo;
    }

    /**
     * Setter for $hostInfo
     * @param string $hostInfo
     */
    public function setHostInfo($hostInfo)
    {
        $this->_hostInfo = $hostInfo;
    }

    /**
     * Getter for $homeUrl
     * @return string
     */
    public function getHomeUrl() {
        return $this->_homeUrl;
    }

    /**
     * Setter for $homeUrl
     * @param string $homeUrl
     */
    public function setHomeUrl($homeUrl) {
        $this->_homeUrl = $homeUrl;
    }

    /**
     * Extract static part from url (without placeholders)
     * @param string $path
     * @return bool|string
     */
    public function extractStaticPart($path) {
        $pos = $this->findPlaceholderStartPos($path);
        if ($pos !== false) {
            $prefix = substr($path, 0, $pos);
            return strlen($prefix) > 1 ? rtrim($prefix, '/') : $prefix;
        }
        return $path;
    }

    /**
     * Finds a position with first placeholder
     * @param string $path
     * @return bool|int
     */
    protected function findPlaceholderStartPos($path) {
        $brFgPos = strpos($path, '{');
        $brSqPos = strpos($path, '[');
        if ($brFgPos === false && $brSqPos === false) {
            return false;
        }
        $len = strlen($path);
        $positions = [
            $brFgPos !== false ? $brFgPos : $len,
            $brSqPos !== false ? $brSqPos : $len,
        ];
        return min($positions);
    }

    /**
     * Build route path with placeholders replace and searching in router paths
     * @param string $path
     * @param array  $params
     * @return string
     */
    protected function buildRoutePath($path, &$params) {
        $path = $this->replacePlaceholders($path, $params);
        $path = $this->searchInRouter($path, $params);
        return $path;
    }

    /**
     * Search in router for predefined paths
     * @param string $path
     * @param array  $params
     * @return string
     */
    protected function searchInRouter($path, &$params) {
        if (isset(\Reaction::$app->router->routePaths[$path])) {
            $paramsKeys = array_keys($params);
            $routes = \Reaction::$app->router->routePaths[$path];
            foreach ($routes as $routeData) {
                $intersect = array_intersect($paramsKeys, $routeData['params']);
                if (count($intersect) === count($routeData['params'])) {
                    $path = $this->replacePlaceholders($routeData['exp'], $params);
                    break;
                }
            }
            return $path;
        }
        return $path;
    }

    /**
     * Replace placeholders in path
     * @param string $path
     * @param array  $params
     * @return string
     */
    protected function replacePlaceholders($path, &$params) {
        if (strpos($path, '{') === false) {
            return $path;
        }
        $path = preg_replace_callback('/(\{([a-zA-Z0-9]+)\})/i', function ($matches) use (&$params) {
            $match = $matches[1];
            $key = $matches[2];
            if (!isset($params[$key])) {
                return $match;
            }
            $replace = $params[$key];
            unset($params[$key]);
            return $replace;
        }, $path);
        return $path;
    }
}