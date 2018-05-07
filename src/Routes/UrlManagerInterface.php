<?php

namespace Reaction\Routes;

use Reaction\Web\AppRequestInterface;

/**
 * Interface UrlManagerInterface
 * @package Reaction\Routes
 * @property string $suffix   URL suffix
 * @property string $baseUrl  Base URL prefix
 * @property string $hostInfo Host information
 */
interface UrlManagerInterface
{
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
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param AppRequestInterface|null $request
     * @return string the created URL
     * @see createAbsoluteUrl()
     */
    public function createUrl($params, $request = null);

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
     */
    public function createAbsoluteUrl($params, $scheme = null, $request = null);

    /**
     * Getter for $baseUrl
     * @param AppRequestInterface|null $request
     * @return string
     */
    public function getBaseUrl($request = null);

    /**
     * Setter for $baseUrl
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl);

    /**
     * Getter for $hostInfo
     * @param AppRequestInterface|null $request
     * @return string
     */
    public function getHostInfo($request = null);

    /**
     * Setter for $hostInfo
     * @param string $hostInfo
     */
    public function setHostInfo($hostInfo);

    /**
     * Getter for $homeUrl
     * @return string
     */
    public function getHomeUrl();

    /**
     * Setter for $homeUrl
     * @param string $homeUrl
     */
    public function setHomeUrl($homeUrl);
}