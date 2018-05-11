<?php

namespace Reaction;

use Psr\Http\Message\ServerRequestInterface;
use Reaction;
use Reaction\DI\ServiceLocator;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Routes\RouteInterface;

/**
 * Class RequestApplication
 * @package Reaction
 */
class RequestApplication extends ServiceLocator implements RequestApplicationInterface, Reaction\DI\ServiceLocatorAutoloadInterface
{
    /** @var ServerRequestInterface Application request instance */
    public $request;
    /**
     * @var string Application request charset
     */
    public $charset = 'UTF-8';
    /**
     * @var string Application request language
     */
    public $language = 'en_US';
    /**
     * @var string Application home URL
     */
    public $homeUrl;

    /** @var RouteInterface current route */
    protected $_route;

    /**
     * Get route for request
     * @return RouteInterface
     * @throws InvalidConfigException
     */
    public function getRoute() {
        if (!isset($this->_route)) {
            $data = Reaction::$app->router->getDispatcherData($this);
            $this->_route = Reaction::create([
                'class' => RouteInterface::class,
                'app' => $this,
                'dispatchedData' => $data,
            ]);
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

    public function resolveRequest()
    {

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
        } elseif (\Reaction\Helpers\ArrayHelper::isIndexed($definition) && count($definition) === 2) {
            $config = $definition[0];
            $params = $definition[1];
        } else {
            $config = $definition;
        }

        if (is_array($config)) {
            $config = \Reaction\Helpers\ArrayHelper::merge(['app' => $this], $config);
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
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: ".gettype($definition));
        }
    }
}