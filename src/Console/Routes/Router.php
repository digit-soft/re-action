<?php

namespace Reaction\Console\Routes;

use FastRoute\Dispatcher;
use Reaction;
use Reaction\RequestApplicationInterface;
use Reaction\Routes\RouterAbstract;
use Reaction\Routes\RouterInterface;

/**
 * Class Router
 * @package Reaction\Console\Routes
 * You can use controllerMap for this router
 *      'controllerMap' => [
 *         'migrate' => [
 *             'class' => 'Reaction\Console\Controllers\MigrateController',
 *             'migrationNamespaces' => [
 *                 'App\Migrations',
 *                 'Some\Namespace\Migrations',
 *             ],
 *         ],
 *     ],
 */
class Router extends RouterAbstract implements RouterInterface
{

    public $controllerNamespaces = [
        'Reaction\Console\Controllers',
    ];
    /**
     * @var array Controllers map
     */
    public $controllerMap = [];

    /** @var array|string|object Error controller */
    public $_errorController = [
        'class' => 'Reaction\Console\Routes\ErrorController',
    ];
    /**
     * @var string Default controller if none matches
     */
    public $defaultController = 'Reaction\Console\Controllers\HelpController';

    /**
     * Search for a given route
     * @param RequestApplicationInterface $app
     * @param string                      $routePath
     * @param string                      $method
     * @return array
     */
    public function searchRoute(RequestApplicationInterface $app, $routePath, $method = 'GET')
    {
        /** @var Controller $controller */
        list($controller, $actionName) = $this->createController($routePath, ['app' => $app]);
        //Params are ignored by console controllers (parameters extracted from command line on action run)
        return [static::ERROR_OK, $controller, $actionName, []];
    }

    /**
     * @inheritdoc
     */
    public function createController($path, $config = [], $defaultOnFault = true)
    {
        $parts = explode('/', trim($path, '/'));
        $actionId = count($parts) >= 2 ? mb_strtolower(array_pop($parts)) : null;
        $controllerPart = mb_strtolower(array_pop($parts));
        if (is_string($controllerPart) && isset($this->controllerMap[$controllerPart])) {
            $configMap = $this->controllerMap[$controllerPart];
            $configMap = is_string($configMap) ? ['class' => $configMap] : $configMap;
            $config = Reaction\Helpers\ArrayHelper::merge($configMap, $config);
            $controller = Reaction::create($config);
            list($controller, $actionId) = $this->createControllerInstance($controller, $actionId, $config);
            return $controller !== null
                ? [$controller, $actionId]
                : ($defaultOnFault ? $this->getDefaultControllerAndAction($config) : [null, null]);
        }
        return parent::createController($path, $config, $defaultOnFault);
    }
}