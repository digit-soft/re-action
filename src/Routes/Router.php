<?php

namespace Reaction\Routes;

use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Annotations\Ctrl;
use Reaction\Annotations\CtrlAction;
use Reaction\Base\BaseObject;
use FastRoute\BadRouteException;
use FastRoute\Dispatcher;
use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\ClassFinderHelper;
use Reaction\Promise\Promise;
use Reaction\Web\AppRequestInterface;
use Reaction\Web\Response;

class Router extends BaseObject implements RouterInterface
{
    public $controllerNamespaces = [
        'App\Controllers',
    ];
    public $dispatcherClass = '\FastRoute\simpleDispatcher';
    public $dispatcherOptions = [];

    public $_errorController = [
        'class' => 'Reaction\Routes\ErrorController',
    ];

    private $routes = [];
    private $errorHandlers = [];
    private $groupCurrent = '';

    /** @var Dispatcher */
    private $dispatcher;
    /** @var array Registered controllers */
    protected $controllers = [];

    /**
     * Add route handling
     * @param string|string[] $httpMethod
     * @param string $route
     * @param mixed $handler
     */
    public function addRoute($httpMethod, $route, $handler) {
        $route = $this->groupCurrent . $route;
        $this->routes[] = [
            'httpMethod' => $httpMethod,
            'route' => $route,
            'handler' => $handler,
        ];
    }

    /**
     * GET method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function get($route, $handler) {
        return $this->addRoute('GET', $route, $handler);
    }

    /**
     * POST method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function post($route, $handler) {
        return $this->addRoute('POST', $route, $handler);
    }

    /**
     * DELETE method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function delete($route, $handler) {
        return $this->addRoute('DELETE', $route, $handler);
    }

    /**
     * HEAD method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function head($route, $handler) {
        return $this->addRoute('HEAD', $route, $handler);
    }

    /**
     * PATCH method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function patch($route, $handler) {
        return $this->addRoute('PATCH', $route, $handler);
    }

    /**
     * PUT method shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function put($route, $handler) {
        return $this->addRoute('PUT', $route, $handler);
    }

    /**
     * All methods shortcut for ::addRoute()
     * @see Router::addRoute()
     * @param string $route
     * @param mixed  $handler
     */
    public function any($route, $handler) {
        return $this->addRoute(['GET', 'POST', 'DELETE', 'PUT', 'PATCH', 'HEAD'], $route, $handler);
    }

    /**
     * Create a routes group
     * @param string $groupPrefix
     * @param mixed  $callback
     */
    public function addGroup($groupPrefix, $callback) {
        if(!is_callable($callback) && !is_string($callback)) {
            throw new BadRouteException(sprintf("Not valid callback for group '%s'", $groupPrefix));
        }
        $prevGroup = $this->groupCurrent;
        $this->groupCurrent = $groupPrefix;
        $callback($this);
        $this->groupCurrent = $prevGroup;
    }

    /**
     * Add controller with its actions
     * @param Controller $controller
     */
    public function addController(Controller $controller) {
        $this->registerController($controller);
    }

    /**
     * Find controllers in given namespaces and register as routes
     */
    public function registerControllers() {
        $classNames = ClassFinderHelper::findClassesPsr4($this->controllerNamespaces, true);
        foreach ($classNames as $className) {
            $this->registerController($className);
        }
    }

    /**
     * Register all defined routes in dispatcher
     */
    public function publishRoutes() {
        $routes = $this->routes;
        $this->dispatcher = $this->createDispatcher(function (\FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $routeRow) {
                $r->addRoute($routeRow['httpMethod'], $routeRow['route'], $routeRow['handler']);
            }
        });
    }

    /**
     * Dispatch requested route
     * @param AppRequestInterface $request
     * @return ExtendedPromiseInterface
     * @throws \Reaction\Exceptions\InvalidConfigException
     * @throws \ReflectionException
     */
    public function resolveRequest(AppRequestInterface $request) {
        $self = $this;
        $routeInfo = $self->dispatchRequest($request);
        /** @var RouteInterface $route */
        $route = \Reaction::create([
            'class' => RouteInterface::class,
            'dispatchedData' => $routeInfo,
        ]);
        return $route->resolve($request)->then(
            function ($response) use (&$request) {
                return $request->emitAndWait(AppRequestInterface::EVENT_REQUEST_END, [$request])->then(
                    function () use ($response) {
                        return $response;
                    }
                );
            }
        );
    }

    /**
     * Get info about requested route from dispatcher
     * @param AppRequestInterface $request
     * @return array
     */
    protected function dispatchRequest(AppRequestInterface $request) {
        $path = '/' . (string)$request->pathInfo;
        $path = rtrim($path, '/');
        $method = $request->method;
        $routeInfo = $this->dispatcher->dispatch($method, $path);
        return $routeInfo;
    }

    /**
     * Create route dispatcher
     * @param callable|string|array $callback
     * @return mixed
     */
    private function createDispatcher($callback) {
        $function = $this->dispatcherClass;
        return $function($callback, $this->dispatcherOptions);
    }

    /**
     * Register controller in routes
     * @param string|Controller $className
     */
    protected function registerController($className) {
        $_className = is_string($className) ? $className : get_class($className);
        if(in_array($_className, $this->controllers)) return;
        $this->controllers[] = $_className;
        $classAnnotations = \Reaction::$annotations->getClass($className);
        if(isset($classAnnotations[Ctrl::class])) {
            $this->registerControllerWithAnnotations($className, $classAnnotations[Ctrl::class]);
        } else {
            $this->registerControllerNoAnnotations($className);
        }
    }

    /**
     * Register controller that uses Annotations
     * @param string|Controller $className
     * @param Ctrl $ctrlAnnotation
     * @throws \ReflectionException
     */
    protected function registerControllerWithAnnotations($className, Ctrl $ctrlAnnotation) {
        $actions = (new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $actions = array_filter($actions, function ($value) {
            return strpos($value->name, 'action') === 0 ? $value : false;
        });
        $actions = ArrayHelper::getColumn($actions, 'name', false);
        if(empty($actions)) return;
        $controller = is_string($className) ? new $className() : $className;
        for($i = 0; $i < count($actions); $i++) {
            $actionAnnotations = \Reaction::$annotations->getMethod($className, $actions[$i]);
            if(!isset($actionAnnotations[CtrlAction::class])) continue;
            /** @var CtrlAction $ctrlAction */
            $ctrlAction = $actionAnnotations[CtrlAction::class];
            $path = $ctrlAnnotation->group . '/' . ltrim($ctrlAction->path, '/');
            $this->addRoute($ctrlAction->method, $path, [$controller, $actions[$i]]);
        }
    }

    /**
     * Register controller that does not use Annotations
     * @param string|Controller $className
     */
    protected function registerControllerNoAnnotations($className) {
        /** @var Controller $controller */
        $controller = new $className();
        $routes = $controller->routes();
        $group = $controller->group();
        if(empty($routes)) return;
        foreach ($routes as $row) {
            $method = $row['method'];
            $route = $group . $row['route'];
            $handlerName = $row['handler'];
            $this->addRoute($method, $route, [$controller, $handlerName]);
        }
    }

    /**
     * Get controller for errors
     * @return Controller
     * @throws \Reaction\Exceptions\InvalidConfigException
     * @throws \ReflectionException
     */
    public function getErrorController()
    {
        if (!is_object($this->_errorController)) {
            $this->_errorController = \Reaction::create($this->_errorController);
        }
        return $this->_errorController;
    }

    /**
     * Set controller for errors
     * @param string|array $controller
     */
    public function setErrorController($controller)
    {
        $this->_errorController = $controller;
    }
}