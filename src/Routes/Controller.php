<?php

namespace Reaction\Routes;

use Reaction\Base\Component;

/**
 * Class Controller
 * @package Reaction\Routes
 */
class Controller extends Component implements ControllerInterface
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
    public function routes() {
        return [];
    }

    /**
     * Get route group name, if empty no grouping
     * @return string
     */
    public function group() {
        return '';
    }

    /**
     * Register controller actions in router
     * @param Router $router
     */
    public function registerInRouter(Router $router) {
        $routes = $this->routes();
        $group = $this->group();
        if (empty($routes)) {
            return;
        }
        foreach ($routes as $row) {
            $method = $row['method'];
            $route = $group . $row['route'];
            $handlerName = $row['handler'];
            $router->addRoute($method, $route, [$this, $handlerName]);
        }
    }
}