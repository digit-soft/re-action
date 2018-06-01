<?php

namespace Reaction\Console\Routes;

use FastRoute\Dispatcher;
use Reaction;
use Reaction\RequestApplicationInterface;
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
    public $defaultController = 'Reaction\Console\Controllers\HelpController';

    /**
     * Register all defined routes in dispatcher
     */
    public function publishRoutes()
    {
    }

    /**
     * Get data from dispatcher
     * @param RequestApplicationInterface $app Request application
     * @param string                      $routePath URI path to resolve
     * @param string                      $method HTTP request method
     * @return array
     */
    public function getDispatcherData(RequestApplicationInterface $app, $routePath, $method = 'GET')
    {
        list($controller, $actionName) = $this->createController($routePath, ['app' => $app]);
        return [Dispatcher::FOUND, [$controller, $actionName]];
    }
}