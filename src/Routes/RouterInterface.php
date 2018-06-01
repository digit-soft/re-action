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
     * GET method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function get($route, $handler);

    /**
     * POST method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function post($route, $handler);

    /**
     * DELETE method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function delete($route, $handler);

    /**
     * HEAD method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function head($route, $handler);

    /**
     * PATCH method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function patch($route, $handler);

    /**
     * PUT method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function put($route, $handler);

    /**
     * All methods shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function any($route, $handler);

    /**
     * Create a routes group
     * @param string $groupPrefix
     * @param mixed  $callback
     */
    public function addGroup($groupPrefix, $callback);

    /**
     * Add controller with its actions
     * @param Controller $controller
     */
    public function addController(Controller $controller);

    /**
     * Find controllers in given namespaces and register as routes
     */
    public function registerControllers();

    /**
     * Register all defined routes in dispatcher
     */
    public function publishRoutes();

    /**
     * Resolve request
     * @param ServerRequestInterface $request
     * @return PromiseInterface
     */
    public function resolveRequest(ServerRequestInterface $request);

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