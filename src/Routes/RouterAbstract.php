<?php

namespace Reaction\Routes;

use FastRoute\BadRouteException;
use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Reaction;
use Reaction\Annotations\Ctrl;
use Reaction\Annotations\CtrlAction;
use Reaction\Base\Component;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ClassFinderHelper;
use Reaction\Helpers\Inflector;
use Reaction\RequestApplicationInterface;

/**
 * Class RouterAbstract
 * @package Reaction\Routes
 */
abstract class RouterAbstract extends Component implements RouterInterface
{
    /**
     * @var string[]
     */
    public $controllerNamespaces = [
        'App\Controllers',
    ];

    /**
     * @var array|string|object Error controller
     */
    public $_errorController = [
        'class' => 'Reaction\Routes\ErrorController',
    ];

    /**
     * @var string Default controller if none matches
     */
    public $defaultController;

    /** @var array Added routes information */
    protected $routes = [];
    /** @var string Currently processed group */
    protected $groupCurrent = '';
    /** @var Dispatcher */
    protected $dispatcher;
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
        //Save previous group
        $prevGroup = $this->groupCurrent;
        //Set new group
        $this->groupCurrent = $groupPrefix;
        //Call function with Router as argument to add routes
        $callback($this);
        //Restore group
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
    abstract public function publishRoutes();

    /**
     * Dispatch requested route
     * @param ServerRequestInterface $request
     * @return PromiseInterface
     */
    public function resolveRequest(ServerRequestInterface $request) {
        $app = $this->createRequestApplication($request);
        $initPromise = !Reaction::$app->initialized ? Reaction::$app->initPromise : Reaction\Promise\resolve(true);
        return $initPromise->then(
            function() use(&$app) {
                return $app->loadComponents();
            }
        )->then(
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
     * Get data from dispatcher
     * @param RequestApplicationInterface $app
     * @return array
     */
    abstract public function getDispatcherData(RequestApplicationInterface $app);

    /**
     * Get controller for errors
     * @return Controller
     * @throws InvalidConfigException
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
     * Get controller and action from path (Just parse path)
     * @param string $path
     * @param array  $config
     * @param bool   $defaultOnFault
     * @return array
     */
    public function createController($path, $config = [], $defaultOnFault = true)
    {
        $path = trim($path, '/');
        $parts = explode('/', $path);
        if (count($parts) < 2) {
            if (!isset($parts[0]) || trim($parts[0]) === '') {
                return $defaultOnFault ? $this->getDefaultControllerAndAction($config) : [null, null];
            } else {
                $controllerName = mb_strtolower(array_pop($parts));
                $actionId = Controller::$_defaultAction;
            }
        } else {
            $actionId = mb_strtolower(array_pop($parts));
            $controllerName = mb_strtolower(array_pop($parts));
        }
        $controllerName = Inflector::id2camel($controllerName, '-') . 'Controller';
        if (!empty($parts)) {
            array_walk($parts, function(&$value) {
                $value = Inflector::id2camel($value, '-');
            });
            $controllerName = implode('\\', $parts) . '\\' . $controllerName;
        }
        list($controller, $actionId) = $this->createControllerInstance($controllerName, $actionId, $config);
        return $controller !== null
            ? [$controller, $actionId]
            : ($defaultOnFault ? $this->getDefaultControllerAndAction($config) : [null, null]);
    }

    /**
     * Get controller namespace relative to $this::$controllerNamespaces
     * @param string $namespace
     * @return string
     * @see $controllerNamespaces
     */
    public function getRelativeControllerNamespace($namespace)
    {
        $candidate = $namespace;
        foreach ($this->controllerNamespaces as $controllerNamespace) {
            if (strpos($namespace, $controllerNamespace) === 0) {
                $temp = ltrim(substr($namespace, strlen($controllerNamespace)), '\\');
                if (strlen($temp) < strlen($candidate)) {
                    $candidate = $temp;
                }
            }
        }
        return $candidate;
    }

    /**
     * Search controller by name and existing method
     * @param string $controllerName
     * @param string $actionId
     * @param array  $config
     * @return array
     */
    protected function createControllerInstance($controllerName, $actionId = null, $config = [])
    {
        if (!isset($controllerName)) {
            return [null, $actionId];
        }
        $controller = null;
        foreach ($this->controllerNamespaces as $namespace) {
            $controllerNameFull = $namespace . '\\' . $controllerName;
            if (!class_exists($controllerNameFull)) {
                continue;
            }
            try {
                /** @var ControllerInterface $controller */
                $config = Reaction\Helpers\ArrayHelper::merge($config, ['class' => $controllerNameFull]);
                $controller = Reaction::create($config);
                if (!isset($actionId) || !$controller->hasAction($actionId)) {
                    $actionId = $controller->defaultAction;
                }
                break;
            } catch (InvalidConfigException $exception) {
                $controller = null;
                continue;
            }
        }
        return [$controller, $actionId];
    }

    /**
     * Create application from request
     * @param ServerRequestInterface $request
     * @return RequestApplicationInterface
     */
    protected function createRequestApplication(ServerRequestInterface $request) {
        $config = Reaction::$config->get('appRequest');
        $config = ['request' => $request] + $config;
        $app = Reaction::createNoExc($config);
        return $app;
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
        $actions = Reaction\Helpers\ArrayHelper::getColumn($actions, 'name', false);
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
     * Get default action
     * @param array $config
     * @return array
     */
    protected function getDefaultControllerAndAction($config = [])
    {
        $controller = $action = null;
        if (isset($this->defaultController)) {
            /** @var ControllerInterface $controller */
            $configDefault = is_array($this->defaultController) ? $this->defaultController : ['class' => $this->defaultController];
            $config = Reaction\Helpers\ArrayHelper::merge($configDefault, $config);
            $controller = Reaction::create($config);
            $action = $controller->defaultAction;
        }
        return [$controller, $action];
    }
}