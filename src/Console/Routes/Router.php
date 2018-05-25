<?php

namespace Reaction\Console\Routes;

use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Reaction;
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
        if (!isset($controllerName) || !isset($actionName)) {
            return [Dispatcher::NOT_FOUND];
        }
        $controller = $this->searchController($controllerName, $actionName);
        if (null === $controller) {
            return [Dispatcher::NOT_FOUND];
        }
        return [Dispatcher::FOUND, [$controller, $actionName]];
    }

    protected function searchController($controllerName, $actionName) {
        $controller = null;
        foreach ($this->controllerNamespaces as $namespace) {
            $controllerNameFull = $namespace . '\\' . $controllerName;
            if (!class_exists($controllerNameFull)) {
                continue;
            }
            try {
                $reflection = new \ReflectionClass($controllerNameFull);
                $hasAction = $reflection->hasMethod($actionName);
            } catch (\ReflectionException $exception) {
                $hasAction = false;
            }
            if(!$hasAction) {
                continue;
            }
            $controller = Reaction::create($controllerNameFull);
        }
        return $controller;
    }

    protected function getControllerFromPathInfo($path) {
        $parts = explode('/', $path);
        if (count($parts) < 2) {
            return [null, null];
        }
        $action = 'action' . ucfirst(array_pop($parts));
        $controller = ucfirst(array_pop($parts)) . 'Controller';
        if (!empty($parts)) {
            array_walk($parts, function(&$value) {
                $value = ucfirst($value);
            });
            $controller = implode('\\', $parts) . '\\' . $controller;
        }
        return [$controller, $action];
    }
}