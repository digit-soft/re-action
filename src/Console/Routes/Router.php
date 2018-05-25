<?php

namespace Reaction\Console\Routes;

use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Reaction;
use Reaction\Helpers\Inflector;
use Reaction\RequestApplicationInterface;
use Reaction\Routes\Controller;
use Reaction\Routes\RouterAbstract;
use Reaction\Routes\RouterInterface;

class Router extends RouterAbstract implements RouterInterface
{

    public $controllerNamespaces = [
        'Reaction\Console\Controllers',
    ];

    /** @var array|string|object Error controller */
    public $_errorController = [
        'class' => 'Reaction\Console\Routes\ErrorController',
    ];
    /**
     * @var string Default controller if none matches
     */
    public $defaultController = 'Reaction\Console\Controllers\DefaultController';

    /**
     * Register all defined routes in dispatcher
     */
    public function publishRoutes()
    {
    }

    /**
     * Get data from dispatcher
     * @param RequestApplicationInterface $app
     * @return array
     */
    public function getDispatcherData(RequestApplicationInterface $app)
    {
        $path = (string)$app->reqHelper->getPathInfo();
        list($controllerName, $actionName) = $this->getControllerFromPathInfo($path);
        list($controller, $actionName) = $this->searchController($controllerName, $actionName);
        if (null === $controller) {
            list($controllerName, $actionName) = $this->getDefaultControllerAndAction();
            $controller = Reaction::create($controllerName);
        }
        return [Dispatcher::FOUND, [$controller, $actionName]];
    }

    /**
     * Search controller by name and existing method
     * @param string $controllerName
     * @param string $actionName
     * @return array|null
     */
    protected function searchController($controllerName, $actionName)
    {
        if (!isset($controllerName) || !isset($actionName)) {
            return [null, $actionName];
        }
        $controller = null;
        $action = $actionName;
        foreach ($this->controllerNamespaces as $namespace) {
            $controllerNameFull = $namespace . '\\' . $controllerName;
            if (!class_exists($controllerNameFull)) {
                continue;
            }
            try {
                $reflection = new \ReflectionClass($controllerNameFull);
                $hasAction = $reflection->hasMethod($actionName);
            } catch (\ReflectionException $exception) {
                continue;
            }
            if(!$hasAction) {
                $action = 'actionHelp';
            }
            $controller = Reaction::create($controllerNameFull);
            break;
        }
        return [$controller, $action];
    }

    /**
     * Get controller class name (not full) and action from path info
     * @param string $path
     * @return array with controller and action names [controller, action]
     */
    protected function getControllerFromPathInfo($path) {
        $parts = explode('/', $path);
        if (count($parts) < 2) {
            return $this->getDefaultControllerAndAction();
        } else {
            $action = mb_strtolower(array_pop($parts));
            $action = 'action' . Inflector::id2camel($action);
            $controller = mb_strtolower(array_pop($parts));
            $controller = Inflector::id2camel($controller) . 'Controller';
            if (!empty($parts)) {
                array_walk($parts, function(&$value) {
                    $value = Inflector::id2camel($value);
                });
                $controller = implode('\\', $parts) . '\\' . $controller;
            }
        }
        return [$controller, $action];
    }

    /**
     * Get default action
     * @return array
     */
    protected function getDefaultControllerAndAction() {
        $controller = $this->defaultController;
        $action = Controller::getActionMethod('');
        return [$controller, $action];
    }
}