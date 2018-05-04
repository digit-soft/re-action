<?php

namespace Reaction\Helpers\Request;

use Reaction;
use Reaction\Exceptions\InvalidArgumentException;

class UrlHelper extends RequestHelperProxy
{
    public $helperClass = 'Reaction\Helpers\Url';

    /**
     * Creates a URL for the given route.
     *
     * This method will use [[\Reaction\Web\UrlManager]] to create a URL.
     *
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```php
     * // generates: /index.php?r=site/index&param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     *
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```php
     * // generates: /index.php?r=site/index&param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * A route may be either absolute or relative. An absolute route has a leading slash (e.g. `/site/index`),
     * while a relative route has none (e.g. `site/index` or `index`). A relative route will be converted
     * into an absolute one by the following rules:
     *
     * - If the route is an empty string, the current [[\yii\web\Controller::route|route]] will be used;
     * - If the route contains no slashes at all (e.g. `index`), it is considered to be an action ID
     *   of the current controller and will be prepended with [[\yii\web\Controller::uniqueId]];
     * - If the route has no leading slash (e.g. `site/index`), it is considered to be a route relative
     *   to the current module and will be prepended with the module's [[\yii\base\Module::uniqueId|uniqueId]].
     *
     * Starting from version 2.0.2, a route can also be specified as an alias. In this case, the alias
     * will be converted into the actual route first before conducting the above transformation steps.
     *
     * Below are some examples of using this method:
     *
     * ```php
     * // /index.php?r=site%2Findex
     * echo Url::toRoute('site/index');
     *
     * // /index.php?r=site%2Findex&src=ref1#name
     * echo Url::toRoute(['site/index', 'src' => 'ref1', '#' => 'name']);
     *
     * // http://www.example.com/index.php?r=site%2Findex
     * echo Url::toRoute('site/index', true);
     *
     * // https://www.example.com/index.php?r=site%2Findex
     * echo Url::toRoute('site/index', 'https');
     *
     * // /index.php?r=post%2Findex     assume the alias "@posts" is defined as "post/index"
     * echo Url::toRoute('@posts');
     * ```
     *
     * @param string|array $route use a string to represent a route (e.g. `index`, `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param bool|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string the generated URL
     * @throws InvalidArgumentException a relative route is given while there is no active controller
     * @see \Reaction\Helpers\Url::toRoute()
     */
    public function toRoute($route, $scheme = false)
    {
        $route = (array) $route;
        $route[0] = static::normalizeRoute($route[0]);

        if ($scheme !== false) {
            return $this->getUrlManager()->createAbsoluteUrl($route, is_string($scheme) ? $scheme : null);
        }

        return $this->getUrlManager()->createUrl($route);
    }

    /**
     * Normalizes route and makes it suitable for UrlManager. Absolute routes are staying as is
     * while relative routes are converted to absolute ones.
     *
     * A relative route is a route without a leading slash, such as "view", "post/view".
     *
     * - If the route is an empty string, the current [[\yii\web\Controller::route|route]] will be used;
     * - If the route contains no slashes at all, it is considered to be an action ID
     *   of the current controller and will be prepended with [[\yii\web\Controller::uniqueId]];
     * - If the route has no leading slash, it is considered to be a route relative
     *   to the current module and will be prepended with the module's uniqueId.
     *
     * Starting from version 2.0.2, a route can also be specified as an alias. In this case, the alias
     * will be converted into the actual route first before conducting the above transformation steps.
     *
     * @param string $route the route. This can be either an absolute route or a relative route.
     * @return string normalized route suitable for UrlManager
     * @throws InvalidArgumentException a relative route is given while there is no active controller
     */
    protected function normalizeRoute($route)
    {
        $route = Reaction::$app->getAlias((string) $route);
        if (strncmp($route, '/', 1) === 0) {
            // absolute route
            return ltrim($route, '/');
        }

        // relative route
        if ($this->request->controller === null) {
            throw new InvalidArgumentException("Unable to resolve the relative route: $route. No active controller is available.");
        }

        if (strpos($route, '/') === false) {
            // empty or an action ID
            return $route === '' ? Yii::$app->controller->getRoute() : Yii::$app->controller->getUniqueId() . '/' . $route;
        }

        // relative to module
        return ltrim(Yii::$app->controller->module->getUniqueId() . '/' . $route, '/');
    }

    /**
     * Creates a URL based on the given parameters.
     *
     * This method is very similar to [[toRoute()]]. The only difference is that this method
     * requires a route to be specified as an array only. If a string is given, it will be treated as a URL.
     * In particular, if `$url` is
     *
     * - an array: [[toRoute()]] will be called to generate the URL. For example:
     *   `['site/index']`, `['post/index', 'page' => 2]`. Please refer to [[toRoute()]] for more details
     *   on how to specify a route.
     * - a string with a leading `@`: it is treated as an alias, and the corresponding aliased string
     *   will be returned.
     * - an empty string: the currently requested URL will be returned;
     * - a normal string: it will be returned as is.
     *
     * When `$scheme` is specified (either a string or `true`), an absolute URL with host info (obtained from
     * [[\yii\web\UrlManager::$hostInfo]]) will be returned. If `$url` is already an absolute URL, its scheme
     * will be replaced with the specified one.
     *
     * Below are some examples of using this method:
     *
     * ```php
     * // /index.php?r=site%2Findex
     * echo Url::to(['site/index']);
     *
     * // /index.php?r=site%2Findex&src=ref1#name
     * echo Url::to(['site/index', 'src' => 'ref1', '#' => 'name']);
     *
     * // /index.php?r=post%2Findex     assume the alias "@posts" is defined as "/post/index"
     * echo Url::to(['@posts']);
     *
     * // the currently requested URL
     * echo Url::to();
     *
     * // /images/logo.gif
     * echo Url::to('@web/images/logo.gif');
     *
     * // images/logo.gif
     * echo Url::to('images/logo.gif');
     *
     * // http://www.example.com/images/logo.gif
     * echo Url::to('@web/images/logo.gif', true);
     *
     * // https://www.example.com/images/logo.gif
     * echo Url::to('@web/images/logo.gif', 'https');
     *
     * // //www.example.com/images/logo.gif
     * echo Url::to('@web/images/logo.gif', '');
     * ```
     *
     *
     * @param array|string $url the parameter to be used to generate a valid URL
     * @param bool|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string the generated URL
     * @throws InvalidArgumentException a relative route is given while there is no active controller
     * @see \Reaction\Helpers\Url::to()
     */
    public function to($url = '', $scheme = false)
    {
        if (is_array($url)) {
            return $this->toRoute($url, $scheme);
        }

        $url = Reaction::$app->getAlias($url);
        if ($url === '') {
            $url = $this->request->getUrl();
        }

        if ($scheme === false) {
            return $url;
        }

        if (static::isRelative($url)) {
            // turn relative URL into absolute
            $url = $this->getUrlManager()->getHostInfo() . '/' . ltrim($url, '/');
        }

        return $this->ensureScheme($url, $scheme);
    }

    /**
     * Normalize URL by ensuring that it use specified scheme.
     *
     * If URL is relative or scheme is not string, normalization is skipped.
     *
     * @param string $url the URL to process
     * @param string $scheme the URI scheme used in URL (e.g. `http` or `https`). Use empty string to
     * create protocol-relative URL (e.g. `//example.com/path`)
     * @return string the processed URL
     * @see \Reaction\Helpers\Url::ensureScheme()
     */
    public function ensureScheme($url, $scheme)
    {
        if (static::isRelative($url) || !is_string($scheme)) {
            return $url;
        }

        if (substr($url, 0, 2) === '//') {
            // e.g. //example.com/path/to/resource
            return $scheme === '' ? $url : "$scheme:$url";
        }

        if (($pos = strpos($url, '://')) !== false) {
            if ($scheme === '') {
                $url = substr($url, $pos + 1);
            } else {
                $url = $scheme . substr($url, $pos);
            }
        }

        return $url;
    }

    /**
     * Returns the base URL of the current request.
     * @param bool|string $scheme the URI scheme to use in the returned base URL:
     *
     * - `false` (default): returning the base URL without host info.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: returning an absolute base URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     * @return string
     * @see \Reaction\Helpers\Url::base()
     */
    public function base($scheme = false)
    {
        $url = $this->getUrlManager()->getBaseUrl();
        if ($scheme !== false) {
            $url = $this->getUrlManager()->getHostInfo() . $url;
            $url = $this->ensureScheme($url, $scheme);
        }

        return $url;
    }

    /**
     * Remembers the specified URL so that it can be later fetched back by [[previous()]].
     *
     * @param string|array $url the URL to remember. Please refer to [[to()]] for acceptable formats.
     * If this parameter is not specified, the currently requested URL will be used.
     * @param string $name the name associated with the URL to be remembered. This can be used
     * later by [[previous()]]. If not set, [[\yii\web\User::setReturnUrl()]] will be used with passed URL.
     * @see previous()
     * @see \yii\web\User::setReturnUrl()
     * @see \Reaction\Helpers\Url::remember()
     */
    public function remember($url = '', $name = null)
    {
        $url = $this->to($url);

        if ($name === null) {
            Yii::$app->getUser()->setReturnUrl($url);
        } else {
            $this->request->session->set($name, $url);
        }
    }

    /**
     * Returns the URL previously [[remember()|remembered]].
     *
     * @param string $name the named associated with the URL that was remembered previously.
     * If not set, [[\yii\web\User::getReturnUrl()]] will be used to obtain remembered URL.
     * @return string|null the URL previously remembered. Null is returned if no URL was remembered with the given name
     * and `$name` is not specified.
     * @see remember()
     * @see \yii\web\User::getReturnUrl()
     * @see \Reaction\Helpers\Url::previous()
     */
    public function previous($name = null)
    {
        if ($name === null) {
            return Yii::$app->getUser()->getReturnUrl();
        }

        return $this->request->session->get($name);
    }

    /**
     * Returns the canonical URL of the currently requested page.
     *
     * The canonical URL is constructed using the current controller's [[\yii\web\Controller::route]] and
     * [[\yii\web\Controller::actionParams]]. You may use the following code in the layout view to add a link tag
     * about canonical URL:
     *
     * ```php
     * $this->registerLinkTag(['rel' => 'canonical', 'href' => Url::canonical()]);
     * ```
     *
     * @return string the canonical URL of the currently requested page
     * @see \Reaction\Helpers\Url::canonical()
     */
    public function canonical()
    {
        $params = Yii::$app->controller->actionParams;
        $params[0] = Yii::$app->controller->getRoute();

        return $this->getUrlManager()->createAbsoluteUrl($params);
    }

    /**
     * Returns the home URL.
     *
     * @param bool|string $scheme the URI scheme to use for the returned URL:
     *
     * - `false` (default): returning a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: returning an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string home URL
     * @see \Reaction\Helpers\Url::home()
     */
    public function home($scheme = false)
    {
        $url = Yii::$app->getHomeUrl();

        if ($scheme !== false) {
            $url = $this->getUrlManager()->getHostInfo() . $url;
            $url = $this->ensureScheme($url, $scheme);
        }

        return $url;
    }

    /**
     * Returns a value indicating whether a URL is relative.
     * A relative URL does not have host info part.
     * @param string $url the URL to be checked
     * @return bool whether the URL is relative
     */
    public static function isRelative($url)
    {
        return strncmp($url, '//', 2) && strpos($url, '://') === false;
    }

    /**
     * Creates a URL by using the current route and the GET parameters.
     *
     * You may modify or remove some of the GET parameters, or add additional query parameters through
     * the `$params` parameter. In particular, if you specify a parameter to be null, then this parameter
     * will be removed from the existing GET parameters; all other parameters specified in `$params` will
     * be merged with the existing GET parameters. For example,
     *
     * ```php
     * // assume $_GET = ['id' => 123, 'src' => 'google'], current route is "post/view"
     *
     * // /index.php?r=post%2Fview&id=123&src=google
     * echo Url::current();
     *
     * // /index.php?r=post%2Fview&id=123
     * echo Url::current(['src' => null]);
     *
     * // /index.php?r=post%2Fview&id=100&src=google
     * echo Url::current(['id' => 100]);
     * ```
     *
     * Note that if you're replacing array parameters with `[]` at the end you should specify `$params` as nested arrays.
     * For a `PostSearchForm` model where parameter names are `PostSearchForm[id]` and `PostSearchForm[src]` the syntax
     * would be the following:
     *
     * ```php
     * // index.php?r=post%2Findex&PostSearchForm%5Bid%5D=100&PostSearchForm%5Bsrc%5D=google
     * echo Url::current([
     *     $postSearch->formName() => ['id' => 100, 'src' => 'google'],
     * ]);
     * ```
     *
     * @param array $params an associative array of parameters that will be merged with the current GET parameters.
     * If a parameter value is null, the corresponding GET parameter will be removed.
     * @param bool|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::$hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http`, `https` or empty string
     *   for protocol-relative URL).
     *
     * @return string the generated URL
     * @see \Reaction\Helpers\Url::current()
     */
    public function current(array $params = [], $scheme = false)
    {
        $currentParams = $this->request->_getQueryParams();
        $currentParams[0] = '/' . Yii::$app->controller->getRoute();
        $route = array_replace_recursive($currentParams, $params);
        return static::toRoute($route, $scheme);
    }

    /**
     * @return \Reaction\Web\UrlManager URL manager used to create URLs
     */
    protected function getUrlManager()
    {
        return $this->request->urlManager;
    }

    /**
     * Get request route
     * @return Reaction\Routes\RouteInterface
     */
    protected function getRoute() {
        return $this->request->getRoute();
    }
}