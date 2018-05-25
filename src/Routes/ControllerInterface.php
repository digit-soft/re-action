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

    /**
     * Get path of action (Caution! With possible RegEx)
     * @param string $action
     * @return null|string
     */
    public function getActionPath($action);

    /**
     * Convert controller action method name to it's ID
     * @param string $actionMethod
     * @return string
     */
    public static function getActionId($actionMethod);

    /**
     * Convert controller action ID to method name
     * @param string $actionId
     * @return string
     */
    public static function getActionMethod($actionId);
}