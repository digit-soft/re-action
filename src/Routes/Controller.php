<?php

namespace Reaction\Routes;

use React\Promise\PromiseInterface;
use Reaction\Annotations\CtrlAction;
use Reaction\Annotations\CtrlActionValidatorInterface;
use Reaction\Base\Component;
use Reaction\Base\ViewContextInterface;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\Http\ForbiddenException;
use Reaction\Exceptions\Http\NotFoundException;
use Reaction\Exceptions\HttpException;
use Reaction\Exceptions\HttpExceptionInterface;
use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\Inflector;
use Reaction\Helpers\ReflectionHelper;
use Reaction\Helpers\StringHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\resolve;
use function Reaction\Promise\all;
use Reaction\RequestApplicationInterface;
use Reaction\Web\Response;
use Reaction\Web\ResponseBuilderInterface;

/**
 * Class Controller
 * @package Reaction\Routes
 */
class Controller extends Component implements ControllerInterface, ViewContextInterface
{
    /**
     * @var string Base view files folder path
     */
    public $baseViewPath;
    /**
     * @var string Layout file path
     */
    public $layout;
    /**
     * @var string Default controller action
     */
    public $defaultAction = 'index';
    /**
     * @var string default controller action
     */
    public static $_defaultAction = 'index';

    /**
     * @var string View files folder path without alias
     */
    protected $_viewPath;
    /**
     * @var string[] Actions list
     */
    protected $_actions;

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
    public function routes()
    {
        return [];
    }

    /**
     * Get route group name, if empty no grouping
     * @return string
     */
    public function group()
    {
        return '';
    }

    /**
     * Get controller ID
     * @return string
     */
    public function getUniqueId()
    {
        $group = $this->group();
        if ($group !== "") {
            return $group;
        }
        $classNameArray = explode('\\', static::class);
        $className = substr(array_pop($classNameArray), 0, -10);
        return Inflector::camel2id($className);
    }

    /**
     * Register controller actions in router
     * @param Router $router
     */
    public function registerInRouter(Router $router)
    {
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

    /**
     * @return string the view path that may be prefixed to a relative view name.
     */
    public function getViewPath()
    {
        if (!isset($this->_viewPath)) {
            $baseViewPath = isset($this->baseViewPath) ? $this->baseViewPath : \Reaction::$app->getViewPath();
            try {
                $reflection = new \ReflectionClass($this);
                $shortName = $reflection->getShortName();
            } catch (\ReflectionException $e) {
                $shortNameExp = explode('\\', static::class);
                $shortName = end($shortNameExp);
            }
            if (strlen($shortName) > 10 && StringHelper::endsWith($shortName, 'Controller')) {
                $shortName = substr($shortName, 0, -10);
            }
            return $baseViewPath . DIRECTORY_SEPARATOR . lcfirst($shortName);
        }
        return $this->_viewPath;
    }

    /**
     * Resolve controller action
     * @param string                      $action
     * @param RequestApplicationInterface $app
     * @param mixed                       ...$params
     * @return mixed
     * @throws NotFoundException
     */
    public function resolveAction(RequestApplicationInterface $app, string $action, ...$params)
    {
        $action = $this->normalizeActionName($action);
        $actionId = static::getActionId($action);
        if (!in_array($app, $params)) {
            array_unshift($params, $app);
        }
        $self = $this;
        return $this->validateAction($action, $app)->then(
            function() use (&$app, &$self, $action, $actionId, $params) {
                if ($this->beforeAction($actionId)) {
                    $app->view->context = $self;
                    return \Reaction::$di->invoke([$self, $action], $params);
                } else {
                    throw new Exception("Before action error");
                }
            },
            function($error) {
                if (!$error instanceof \Throwable) {
                    $error = new ForbiddenException('You can not perform this action');
                }
                throw $error;
            }
        )->then(
            function($result = null) use ($actionId) {
                return $this->afterAction($actionId, $result);
            }
        );
    }

    /**
     * Resolve error
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     * @param bool                        $asPlainText
     * @return ExtendedPromiseInterface|ResponseBuilderInterface
     */
    public function resolveError(RequestApplicationInterface $app, \Throwable $exception, $asPlainText = false)
    {
        $app->view->context = $this;
        if ($asPlainText) {
            return $this->resolveErrorAsPlainText($app, $exception);
        }
        $format = $app->response->format;
        switch ($format) {
            case Response::FORMAT_RAW:
            case Response::FORMAT_HTML:
                return $this->resolveErrorAsHtml($app, $exception);
            default:
                return $this->resolveErrorAsArray($app, $exception);
        }
    }

    /**
     * This method is invoked right before an action is executed.
     *
     * The method will trigger the [[EVENT_BEFORE_ACTION]] event. The return value of the method
     * will determine whether the action should continue to run.
     *
     * In case the action should not run, the request should be handled inside of the `beforeAction` code
     * by either providing the necessary output or redirecting the request. Otherwise the response will be empty.
     *
     * If you override this method, your code should look like the following:
     *
     * ```php
     * public function beforeAction($action)
     * {
     *     // your custom code here, if you want the code to run before action filters,
     *     // which are triggered on the [[EVENT_BEFORE_ACTION]] event, e.g. PageCache or AccessControl
     *
     *     if (!parent::beforeAction($action)) {
     *         return false;
     *     }
     *
     *     // other custom code here
     *
     *     return true; // or false to not run the action
     * }
     * ```
     *
     * @param string $actionId the action to be executed.
     * @return bool whether the action should continue to run.
     */
    public function beforeAction($actionId)
    {
        $isValid = true;
        $this->emit(self::EVENT_BEFORE_ACTION, [&$this, $actionId, &$isValid]);
        return $isValid;
    }

    /**
     * This method is invoked right after an action is executed.
     *
     * The method will trigger the [[EVENT_AFTER_ACTION]] event. The return value of the method
     * will be used as the action return value.
     *
     * If you override this method, your code should look like the following:
     *
     * ```php
     * public function afterAction($action, $result)
     * {
     *     $result = parent::afterAction($action, $result);
     *     // your custom code here
     *     return $result;
     * }
     * ```
     *
     * @param string $actionId the action just executed.
     * @param mixed  $result the action return result.
     * @return mixed the processed action result.
     */
    public function afterAction($actionId, $result = null)
    {
        $this->emit(self::EVENT_AFTER_ACTION, [&$this, $actionId, &$result]);
        return $result;
    }

    /**
     * Render view
     * @param RequestApplicationInterface $app
     * @param string                      $viewName
     * @param array                       $params
     * @param bool                        $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function render(RequestApplicationInterface $app, $viewName, $params = [], $asString = false)
    {
        return $this->renderInLayout($app, $viewName, $params, $asString);
    }

    /**
     * Render view partially
     * @param RequestApplicationInterface $app
     * @param string                      $viewName
     * @param array                       $params
     * @param bool                        $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function renderPartial(RequestApplicationInterface $app, $viewName, $params = [], $asString = false)
    {
        return $this->renderInternal($app, $viewName, $params, false, $asString);
    }

    /**
     * Render for AJAX request
     * @param RequestApplicationInterface $app
     * @param string                      $viewName
     * @param array                       $params
     * @param bool                        $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function renderAjax(RequestApplicationInterface $app, $viewName, $params = [], $asString = false)
    {
        return $this->renderInternal($app, $viewName, $params, true, $asString);
    }

    /**
     * Get path of action (Caution! With possible RegEx)
     * @param string $action
     * @return null|string
     */
    public function getActionPath($action)
    {
        try {
            $action = $this->normalizeActionName($action);
        } catch (NotFoundException $e) {
            return null;
        }
        $annotations = \Reaction::$annotations->getMethod($action, $this);
        if (isset($annotations[CtrlAction::class])) {
            /** @var CtrlAction $actionAnnotation */
            $actionAnnotation = $annotations[CtrlAction::class];
            return $actionAnnotation->path;
        } else {
            $routes = ArrayHelper::map($this->routes(), 'handler', 'route');
            if (isset($routes[$action])) {
                return $routes[$action];
            }
        }
        return null;
    }

    /**
     * Get actions list
     * @return string[]
     */
    public function actions()
    {
        if (!isset($this->_actions)) {
            $reflection = ReflectionHelper::getClassReflection($this);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            $actions = [];
            foreach ($methods as $method) {
                $name = $method->getName();
                if ($name !== 'actions' && !$method->isStatic() && strncmp($name, 'action', 6) === 0) {
                    $actions[] = static::getActionId($name);
                }
            }
            $this->_actions = $actions;
        }
        return $this->_actions;
    }

    /**
     * Check that controller has action with given ID
     * @param string $actionId
     * @return bool
     */
    public function hasAction($actionId)
    {
        $actions = $this->actions();
        return in_array($actionId, $actions);
    }

    /**
     * Render view in layout
     * @param RequestApplicationInterface $app
     * @param string                      $viewName
     * @param array                       $params
     * @param bool                        $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    protected function renderInLayout(RequestApplicationInterface $app, $viewName, $params = [], $asString = false)
    {
        $view = $app->view;
        if (isset($this->layout)) {
            $layoutFile = $view->findViewFile($this->layout, $this);
        } else {
            $layoutFile = $view->findViewFile($view->layout, $this);
        }
        $content = $this->renderInternal($app, $viewName, $params, false, true);
        $rendered = $this->renderFile($app, $layoutFile, ['content' => $content]);
        if ($asString) {
            return $rendered;
        }
        $app->response->setBody($rendered);
        return $app->response;
    }

    /**
     * Render view internal function
     * @param RequestApplicationInterface $app
     * @param string                      $viewName
     * @param array                       $params
     * @param bool                        $ajax
     * @param bool                        $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    protected function renderInternal(RequestApplicationInterface $app, $viewName, $params = [], $ajax = false, $asString = false)
    {
        $view = $app->view;
        $rendered = $ajax ? $view->renderAjax($viewName, $params, $this) : $view->render($viewName, $params, $this);
        if ($asString) {
            return $rendered;
        }
        $app->response->setBody($rendered);
        return $app->response;
    }

    /**
     * Render file
     * @param RequestApplicationInterface $app
     * @param string                      $file
     * @param array                       $params
     * @return string
     */
    protected function renderFile(RequestApplicationInterface $app, $file, $params = [])
    {
        return $app->view->renderFile($file, $params, $this);
    }

    /**
     * Normalize action name
     * @param string $action
     * @param bool   $throwException
     * @return string|null
     * @throws NotFoundException
     */
    protected function normalizeActionName($action, $throwException = true)
    {
        if (!StringHelper::startsWith($action, 'action')) {
            $action = strpos($action, '-') ? Inflector::id2camel($action) : $action;
            $action = 'action' . ucfirst($action);
        }

        if (!method_exists($this, $action)) {
            if ($throwException) {
                throw new NotFoundException('Page not found');
            } else {
                return null;
            }
        }

        return $action;
    }

    /**
     * Resolve error as rendered html
     * @param RequestApplicationInterface $app
     * @param \Throwable          $exception
     * @return ResponseBuilderInterface|ExtendedPromiseInterface
     */
    protected function resolveErrorAsHtml(RequestApplicationInterface $app, \Throwable $exception)
    {
        $actions = ['actionError'];
        if ($exception instanceof HttpException) {
            $actions[] = 'actionErrorHttp';
            $actions[] = 'actionErrorHttp' . $exception->statusCode;
        }
        $action = null;
        foreach ($actions as $possibleAction) {
            $action = $this->normalizeActionName($possibleAction, false);
            if (isset($action)) {
                break;
            }
        }
        if (!isset($action)) {
            return $this->resolveErrorAsPlainText($app, $exception);
        }
        return \Reaction::$di->invoke([$this, $action], [$app, $exception]);
    }

    /**
     * Resolve error as array formatted
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     * @return ResponseBuilderInterface
     */
    protected function resolveErrorAsArray(RequestApplicationInterface $app, \Throwable $exception)
    {
        $data = $this->getErrorData($exception);
        $responseData = ['error' => $data];
        $app->response->setBody($responseData)->setStatusCodeByException($exception);
        return $app->response;
    }

    /**
     * Resolve error as pain text
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     * @return ResponseBuilderInterface
     */
    protected function resolveErrorAsPlainText(RequestApplicationInterface $app, \Throwable $exception)
    {
        $data = $this->getErrorData($exception);
        $responseData = ['error' => $data];
        $app->response->setBody(print_r($responseData, true))
            ->setStatusCodeByException($exception)
            ->setFormat(Response::FORMAT_RAW);
        return $app->response;
    }

    /**
     * Get error data as array
     * @param \Throwable $exception
     * @return array
     */
    protected function getErrorData(\Throwable $exception)
    {
        $data = [
            'message' => $exception->getMessage(),
            'code' => $exception instanceof HttpExceptionInterface ? $exception->statusCode : $exception->getCode(),
            'name' => $this->getExceptionName($exception),
        ];
        if (\Reaction::isDebug() || \Reaction::isDev()) {
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $data['trace'] = $exception->getTraceAsString();
        }
        return $data;
    }

    /**
     * Get name of exception
     * @param \Throwable $exception
     * @return string
     */
    protected function getExceptionName($exception)
    {
        if ($exception instanceof Exception) {
            return $exception->getName();
        } else {
            $classNameExp = explode('\\', get_class($exception));
            return end($classNameExp);
        }
    }

    /**
     * Validate that user can perform that action
     * @param string $action
     * @param RequestApplicationInterface $app
     * @return ExtendedPromiseInterface
     */
    protected function validateAction($action, RequestApplicationInterface $app)
    {
        $annotationsCtrl = \Reaction::$annotations->getClass($this);
        $annotationsAction = \Reaction::$annotations->getMethod($action, $this);
        $annotations = ArrayHelper::merge(array_values($annotationsCtrl), array_values($annotationsAction));
        $promises = [];
        if (!empty($annotations)) {
            foreach ($annotations as $annotation) {
                if (!$annotation instanceof CtrlActionValidatorInterface) {
                    continue;
                }
                $promise = $annotation->validate($app);
                if (!$promise instanceof PromiseInterface) {
                    $promise = !empty($promise) ? \Reaction\Promise\resolve(true) : \Reaction\Promise\reject(false);
                    \Reaction::warning('not PR');
                }
                $promises[] = $promise;
                $promises[] = \Reaction\Promise\resolve(true);
            }
        }
        if (empty($promises)) {
            return resolve(true);
        }
        $all = \Reaction\Promise\all($promises);
        return $all;
    }

    /**
     * Convert controller action method name to it's ID
     * @param string $actionMethod
     * @return string
     */
    public static function getActionId($actionMethod)
    {
        if (strpos($actionMethod, 'action') === 0) {
            $actionMethod = substr($actionMethod, 6);
        }
        return Inflector::camel2id($actionMethod, '-');
    }

    /**
     * Convert controller action ID to method name
     * @param string $actionId
     * @return string
     */
    public static function getActionMethod($actionId = '')
    {
        if ($actionId === '') {
            $actionId = static::$_defaultAction;
        }
        $actionMethod = Inflector::id2camel($actionId, '-');
        return 'action' . $actionMethod;
    }
}