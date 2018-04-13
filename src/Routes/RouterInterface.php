<?php

namespace Reaction\Routes;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface RouterInterface
 * @package Reaction\Routes
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
     * Dispatch requested route
     * @param ServerRequestInterface $request
     * @return \app\base\web\Response
     */
    public function dispatchRoute(ServerRequestInterface $request);
}