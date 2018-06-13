<?php

namespace Reaction;

use Psr\Http\Message\ServerRequestInterface;
use Reaction;
use Reaction\DI\ServiceLocator;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ArrayHelper;
use Reaction\Routes\RouteInterface;

/**
 * Class RequestApplication
 * @package Reaction
 */
class RequestApplication extends ServiceLocator implements RequestApplicationInterface, Reaction\DI\ServiceLocatorAutoloadInterface
{
    /**
     * @var ServerRequestInterface Application request instance
     */
    public $request;
    /**
     * @var string Application request charset
     */
    public $charset = 'UTF-8';
    /**
     * @var string Application request language
     */
    public $language = 'en-US';
    /**
     * @var string Application home URL
     */
    public $homeUrl;

    /** @var RouteInterface current route */
    protected $_route;

    /**
     * Get route for request
     * @return RouteInterface
     */
    public function getRoute() {
        if (!isset($this->_route)) {
            $this->createRoute();
        }
        return $this->_route;
    }

    /**
     * Setter for route
     * @param RouteInterface|null $route
     */
    public function setRoute($route = null)
    {
        $this->_route = $route;
    }

    /**
     * Create Route with given params
     * @param string|null $routePath
     * @param string|null $method
     * @param array|null  $params
     * @param bool        $onlyReturn
     * @return RouteInterface
     */
    public function createRoute($routePath = null, $method = null, $params = null, $onlyReturn = false)
    {
        $withInternalCtrl = $routePath !== null;
        $routePath = isset($routePath) ? $routePath : $this->reqHelper->getPathInfo();
        $method = isset($method) ? $method : $this->reqHelper->getMethod();
        $data = Reaction::$app->router->searchRoute($this, $routePath, $method, $withInternalCtrl);
        //Parameters overwrite
        if (is_array($params) && count($data) >= 3) {
            $data[3] = $params;
        }
        $route = Reaction::create([
            'class' => RouteInterface::class,
            'app' => $this,
            'dispatchedData' => $data,
        ]);
        if (!$onlyReturn) {
            $this->_route = $route;
        }
        return $route;
    }

    /**
     * Resolve app action
     * @param string|null $routePath
     * @param string|null $method
     * @param array|null $params
     * @return Promise\ExtendedPromiseInterface
     */
    public function resolveAction($routePath = null, $method = null, $params = null)
    {
        $route = $this->createRoute($routePath, $method, $params);
        return $route->resolve();
    }

    /**
     * Get app URL manager
     * @return \Reaction\Web\UrlManager
     * @throws InvalidConfigException
     */
    public function getUrlManager()
    {
        /** @var Reaction\Web\UrlManager $manager */
        $manager = $this->get('urlManager');
        return $manager;
    }

    /**
     * Get app home URL
     * @return string
     */
    public function getHomeUrl()
    {
        return isset($this->homeUrl) ? $this->homeUrl : Reaction::$app->urlManager->getHomeUrl();
    }

    /**
     * Registers a component definition with this locator.
     * Overridden to inject application instance
     * @param string $id
     * @param mixed  $definition
     * @throws InvalidConfigException
     */
    public function set($id, $definition)
    {
        unset($this->_components[$id]);

        if ($definition === null) {
            unset($this->_definitions[$id]);
            return;
        }

        //Extract definition from DI Definition
        if ($definition instanceof \Reaction\DI\Definition) {
            $definition = $definition->dumpArrayDefinition();
        }

        if (is_string($definition)) {
            $config = ['class' => $definition];
        } elseif (ArrayHelper::isIndexed($definition) && count($definition) === 2) {
            $config = $definition[0];
            $params = $definition[1];
        } else {
            $config = $definition;
        }

        if (is_array($config)) {
            $config = ArrayHelper::merge(['app' => $this], $config);
            $definition = isset($params) ? [$config, $params] : $config;
        }

        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;
            } else {
                throw new InvalidConfigException("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    /**
     * Translates a message to the specified language.
     *
     * @param string $category Message category
     * @param string $message  Message for translation
     * @param array  $params   Parameters array
     * @param string $language Language translate to
     * @return string
     */
    public function t($category, $message, $params = [], $language = null)
    {
        $language = isset($language) ? $language : $this->language;
        return Reaction::t($category, $message, $params, $language);
    }
}