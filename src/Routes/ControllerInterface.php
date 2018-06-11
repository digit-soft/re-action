<?php

namespace Reaction\Routes;
use Reaction\RequestApplicationInterface;

/**
 * Interface ControllerInterface
 * @package Reaction\Routes
 * @property string $viewPath
 * @property string $defaultAction
 */
interface ControllerInterface
{
    /**
     * @event ActionEvent an event raised right before executing a controller action.
     * You may set [[$isValid]] to be false to cancel the action execution.
     */
    const EVENT_BEFORE_ACTION = 'beforeAction';
    /**
     * @event ActionEvent an event raised right after executing a controller action.
     */
    const EVENT_AFTER_ACTION = 'afterAction';

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
     * Get controller ID
     * @return string
     */
    public function getUniqueId();

    /**
     * Get current action
     * @param RequestApplicationInterface|null $app
     * @return string|null
     */
    public function getCurrentAction(RequestApplicationInterface $app = null);

    /**
     * Register controller actions in router
     * @param Router $router
     */
    public function registerInRouter(Router $router);

    /**
     * Resolve controller action
     * @param string                      $action
     * @param RequestApplicationInterface $app
     * @param mixed                       ...$params
     * @return mixed
     */
    public function resolveAction(RequestApplicationInterface $app, string $action, ...$params);

    /**
     * Get actions list
     * @return string[]
     */
    public function actions();

    /**
     * Check that controller has action with given ID
     * @param string $actionId
     * @return bool
     */
    public function hasAction($actionId);

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