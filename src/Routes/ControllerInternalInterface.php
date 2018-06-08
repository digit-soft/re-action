<?php
/**
 * Created by PhpStorm.
 * User: digit
 * Date: 08.06.18
 * Time: 10:12
 */

namespace Reaction\Routes;

/**
 * Interface ControllerInternalInterface.
 * You can mark controller with this interface to not show it to user on request,
 * just for manual call through Reaction::$app->router->searchRoute($app, $routePath, $method, TRUE)
 * @package Reaction\Routes
 */
interface ControllerInternalInterface
{

}