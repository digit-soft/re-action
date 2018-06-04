<?php

namespace Reaction\Routes;

use FastRoute\RouteParser;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Reaction\RequestApplicationInterface;

/**
 * Interface RouterInterface
 * @package Reaction\Routes
 * @property Controller  $errorController
 * @property RouteParser $routeParser
 * @property array       $routePaths
 * @property array       $controllerNamespaces
 */
interface RouterInterface
{
    /**
     * Add route handling
     * @param string|string[] $httpMethod
     * @param string $route
     * @param mixed $handler
     */
    public function addRoute($httpMethod, $route, $handler);

    /**
     * Add controller with its actions
     * @param Controller $controller
     */
    public function addController(Controller $controller);

    /**
     * Find controllers in given namespaces and register as routes
     */
    public function initRoutes();

    /**
     * Get controller for errors
     * @return Controller
     */
    public function getErrorController();

    /**
     * Set controller for errors
     * @param string|array $controller
     */
    public function setErrorController($controller);

    /**
     * Get data from dispatcher
     * @param RequestApplicationInterface $app Request application
     * @param string                      $routePath URI path to resolve
     * @param string                      $method HTTP request method
     * @return array
     */
    public function getDispatcherData(RequestApplicationInterface $app, $routePath, $method = 'GET');

    /**
     * Get controller and action from path (Just parse path)
     * @param string $path
     * @param array  $config
     * @param bool   $defaultOnFault
     * @return array
     */
    public function createController($path, $config = [], $defaultOnFault = true);

    /**
     * Get controller namespace relative to $this::$controllerNamespaces
     * @param string $namespace
     * @return string
     * @see Router::$controllerNamespaces
     */
    public function getRelativeControllerNamespace($namespace);
}