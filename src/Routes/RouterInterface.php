<?php

namespace Reaction\Routes;

use Reaction\RequestApplicationInterface;

/**
 * Interface RouterInterface
 * @package Reaction\Routes
 * @property array $routePaths
 * @property array $controllerNamespaces
 */
interface RouterInterface
{
    const ERROR_OK                  = 200; //No error
    const ERROR_NOT_FOUND           = 404; //Page (controller|action) not found
    const ERROR_METHOD_NOT_ALLOWED  = 400; //Method not allowed for this action

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
     * Search for a given route
     * @param RequestApplicationInterface $app
     * @param string                      $routePath
     * @param string                      $method
     * @param bool                        $withInternal
     * @return array
     */
    public function searchRoute(RequestApplicationInterface $app, $routePath, $method = 'GET', $withInternal = false);

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