<?php

namespace Reaction\Routes;

use FastRoute\Dispatcher;
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
     * Add controller with its actions
     * @param Controller $controller
     */
    public function addController(Controller $controller) {
        $this->registerController($controller);
    }

    /**
     * Find controllers in given namespaces and register as routes
     */
    public function initRoutes()
    {
        $classNames = ClassFinderHelper::findClassesPsr4($this->controllerNamespaces, true);
        foreach ($classNames as $className) {
            $this->registerController($className);
        }
    }

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
     * Get router path expressions
     * @return array
     */
    public function getRoutePaths()
    {
        return isset($this->_routePaths) ? $this->_routePaths : [];
    }

    /**
     * Search for a given route
     * @param RequestApplicationInterface $app
     * @param string                      $routePath
     * @param string                      $method
     * @return array
     */
    abstract public function searchRoute(RequestApplicationInterface $app, $routePath, $method = 'GET');

    /**
     * Search controller by name and action ID
     * @param string|ControllerInterface $controllerName
     * @param string                     $actionId
     * @param array                      $config
     * @return array
     */
    protected function createControllerInstance($controllerName, $actionId = null, $config = [])
    {
        if (!isset($controllerName)) {
            return [null, $actionId];
        } elseif ($controllerName instanceof ControllerInterface) {
            $controller = $controllerName;
            if (!isset($actionId) || !$controller->hasAction($actionId)) {
                $actionId = $controller->defaultAction;
            }
            return [$controller, $actionId];
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
            return $value->name !== 'actions' && strpos($value->name, 'action') === 0 ? $value : false;
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
            $actionId = Controller::getActionId($actions[$i]);
            $this->addRoute($ctrlAction->method, $path, [$controller, $actionId]);
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
            $actionId = Controller::getActionId($handlerName);
            $this->addRoute($method, $route, [$controller, $actionId]);
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