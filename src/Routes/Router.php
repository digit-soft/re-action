<?php

namespace Reaction\Routes;

use Reaction\Annotations\Ctrl;
use Reaction\Annotations\CtrlAction;
use Reaction\Base\BaseObject;
use FastRoute\BadRouteException;
use FastRoute\Dispatcher;
use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\ClassFinder;
use Reaction\Web\Response;
use Psr\Http\Message\ServerRequestInterface;

class Router extends BaseObject implements RouterInterface
{
    public $controllerNamespaces = [
        'App\Controller',
        'app\controller',
    ];
    public $dispatcherClass = '\FastRoute\simpleDispatcher';
    public $dispatcherOptions = [];
    ///** @var Application */
    //public $app;

    private $routes = [];
    private $errorHandlers = [];
    private $groupCurrent = '';

    /** @var Dispatcher */
    private $dispatcher;

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
     * Register all defined routes
     */
    public function registerRoutes() {
        $routes = $this->routes;
        $this->dispatcher = $this->createDispatcher(function (\FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $routeRow) {
                $r->addRoute($routeRow['httpMethod'], $routeRow['route'], $routeRow['handler']);
            }
        });
    }

    /**
     * Dispatch requested route
     * @param ServerRequestInterface $request
     * @return Response
     */
    public function dispatchRoute(ServerRequestInterface $request) {
        //throw new \Exception('test');
        $requestInfo = $this->getRequestInfo($request);
        $routeInfo = $this->dispatcher->dispatch($requestInfo['method'], $requestInfo['path']);
        $response = new Response(200);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response = new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $message = strtr('Method Not Allowed. Only "{methods}"', ['{methods}' => implode(', ', $routeInfo[1])]);
                $response = new Response(405, ['Content-Type' => 'text/plain'],  $message);
                break;
            case Dispatcher::FOUND:
                $params = $routeInfo[2] ?? [];
                $response = $routeInfo[1]($request, ... array_values($params));
                break;
        }
        return $response;
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
     * Get basic information about route
     * @param ServerRequestInterface $request
     * @return array
     */
    private function getRequestInfo(ServerRequestInterface $request) {
        $info = [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
        ];

        $info['query'] = isset($info['query']) ? $info['query'] : [];
        $info['method'] = isset($info['method']) ? strtoupper($info['method']) : 'GET';

        return $info;
    }

    /**
     * Register controller in routes
     * @param string|Controller $className
     */
    protected function registerController($className) {
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
            $path = $ctrlAnnotation->group . ltrim($ctrlAction->path, '/');
            $this->addRoute($ctrlAction->method, $path, [$controller, $actions[$i]]);
            \Reaction::$app->logger->info($ctrlAction);
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
     * Find controllers in given namespaces and register as routes
     */
    public function findControllers() {
        $classNames = ClassFinder::findClassesPsr4($this->controllerNamespaces, true);
        foreach ($classNames as $className) {
            $this->registerController($className);
        }
    }
}