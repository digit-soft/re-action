<?php

namespace Reaction\Routes;

/**
 * Interface ControllerInterface
 * @package Reaction\Routes
 * @property string $viewPath
 */
interface ControllerInterface
{
    /**
     * Routes description
     *
     * Example return:
     * [
     *      [
     *          'method' => ['GET', 'POST'],
     *          'route' => 'test/{id:\d+}',
     *          'handler' => 'actionTest',
     *      ]
     * ]
     *
     * @return array
     */
    public function routes();

    /**
     * Get route group name, if empty no grouping
     * @return string
     */
    public function group();

    /**
     * Register controller actions in router
     * @param Router $router
     */
    public function registerInRouter(Router $router);
}