<?php

namespace Reaction\Routes;

use FastRoute\RouteParser;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Reaction;
use Reaction\Base\Component;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Annotations\Ctrl;
use Reaction\Annotations\CtrlAction;
use FastRoute\BadRouteException;
use FastRoute\Dispatcher;
use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\ClassFinderHelper;
use Reaction\RequestApplicationInterface;

/**
 * Class Router
 * @package Reaction\Routes
 */
class Router extends Component implements RouterInterface
{
    public $controllerNamespaces = [
        'App\Controllers',
    ];
    public $dispatcherClass = '\FastRoute\simpleDispatcher';
    public $dispatcherOptions = [];
    public $routeParserClass = '\FastRoute\RouteParser\Std';
    public $routeParserOptions = [];

    /** @var array|string|object Error controller */
    public $_errorController = [
        'class' => 'Reaction\Routes\ErrorController',
    ];

    private $routes = [];
    private $groupCurrent = '';

    /** @var Dispatcher */
    private $dispatcher;
    /** @var array Registered controllers */
    protected $controllers = [];
    /** @var RouteParser */
    protected $_routeParser;
    /** @var array  Route path expressions. Used to build URLs */
    protected $_routePaths = [];

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
        $this->parseRoutesData();
        $this->dispatcher = $this->createDispatcher(function (\FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $routeRow) {
                $r->addRoute($routeRow['httpMethod'], $routeRow['route'], $routeRow['handler']);
            }
        });
    }

    /**
     * Get data from dispatcher
     * @param RequestApplicationInterface $app
     * @return array
     */
    public function getDispatcherData(RequestApplicationInterface $app) {
        $path = '/' . (string)$app->reqHelper->pathInfo;
        $path = rtrim($path, '/');
        $method = $app->reqHelper->getMethod();
        return $this->dispatcher->dispatch($method, $path);
    }

    /**
     * Dispatch requested route
     * @param ServerRequestInterface $request
     * @return PromiseInterface
     */
    public function resolveRequest(ServerRequestInterface $request) {
        $app = $this->createRequestApplication($request);
        return $app->loadComponents()->then(
            function () use (&$app) {
                return $app->getRoute()->resolve();
            }
        )->then(
            function ($response) use (&$app) {
                return $app->emitAndWait(RequestApplicationInterface::EVENT_REQUEST_END, [$app])->then(
                    function () use ($response) {
                        return $response;
                    }
                );
            }
        );
    }

    /**
     * Get router path expressions
     * @return array
     */
    public function getRoutePaths() {
        return $this->_routePaths;
    }

    /**
     * Create application from request
     * @param ServerRequestInterface $request
     * @return RequestApplicationInterface
     */
    protected function createRequestApplication(ServerRequestInterface $request) {
        $config = Reaction::$config->get('requestApp');
        $config = ['request' => $request] + $config;
        $app = Reaction::createNoExc($config);
        return $app;
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
        if (in_array($_className, $this->controllers)) {
            return;
        }
        $this->controllers[] = $_className;
        $classAnnotations = Reaction::$annotations->getClass($className);
        if (isset($classAnnotations[Ctrl::class])) {
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
        if (empty($actions)) {
            return;
        }
        $controller = is_string($className) ? new $className() : $className;
        for ($i = 0; $i < count($actions); $i++) {
            $actionAnnotations = Reaction::$annotations->getMethod($className, $actions[$i]);
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
        if (empty($routes)) {
            return;
        }
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
     */
    public function getErrorController()
    {
        if (!is_object($this->_errorController)) {
            $this->_errorController = Reaction::create($this->_errorController);
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

    /**
     * Getter for $routeParser
     * @return RouteParser
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    public function getRouteParser() {
        if (!isset($this->_routeParser)) {
            $this->_routeParser = Reaction::create($this->routeParserClass, $this->routeParserOptions);
        }
        return $this->_routeParser;
    }

    /**
     * Parse routes data to obtain path expressions, those used for URL building
     */
    protected function parseRoutesData() {
        $routes = $this->routes;
        foreach ($routes as $routeData) {
            $path = $routeData['route'];
            $this->buildPathExpressions($path, true);
        }
        foreach ($this->_routePaths as &$routePaths) {
            $prevCnt = 0;
            ArrayHelper::multisort($routePaths, function ($row) use (&$prevCnt) {
                return count($row['params']);
            }, SORT_DESC);
        }
    }

    /**
     * Build path expression from router path
     * @param string $path
     * @param bool $store
     * @return array
     */
    protected function buildPathExpressions($path, $store = false) {
        $segments = $this->routeParser->parse($path);
        $expressions = [];
        foreach ($segments as $segmentGroup) {
            $expression = '';
            $params = [];
            $staticPart = '/';
            $staticPartEnded = false;
            foreach ($segmentGroup as $segment) {
                if (is_string($segment)) {
                    $expression .= $segment;
                    if (!$staticPartEnded) {
                        $staticPart .= $segment;
                    }
                } elseif (is_array($segment)) {
                    $staticPartEnded = true;
                    $expression .= '{'.$segment[0].'}';
                    $params[] = $segment[0];
                }
            }
            $staticPart = '/' . trim($staticPart, '/');
            if ($store && !empty($params)) {
                $row = [
                    'exp' => $expression,
                    'params' => $params,
                ];
                $this->_routePaths[$staticPart] = isset($this->_routePaths[$staticPart]) ? $this->_routePaths[$staticPart] : [];
                $this->_routePaths[$staticPart][] = $row;
            }
            $expressions[$expression] = [
                'expression' => $expression,
                'prefix' => $staticPart,
                'params' => $params,
            ];
        }

        return $expressions;
    }
}