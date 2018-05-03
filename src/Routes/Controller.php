<?php

namespace Reaction\Routes;

use Reaction\Base\Component;
use Reaction\Base\ViewContextInterface;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\Http\NotFoundException;
use Reaction\Exceptions\HttpException;
use Reaction\Exceptions\HttpExceptionInterface;
use Reaction\Helpers\StringHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Web\AppRequestInterface;
use Reaction\Web\Response;
use Reaction\Web\ResponseBuilderInterface;

/**
 * Class Controller
 * @package Reaction\Routes
 */
class Controller extends Component implements ControllerInterface, ViewContextInterface
{
    public $baseViewPath;
    public $layout;

    protected $_viewPath;

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
    public function routes() {
        return [];
    }

    /**
     * Get route group name, if empty no grouping
     * @return string
     */
    public function group() {
        return '';
    }

    /**
     * Register controller actions in router
     * @param Router $router
     */
    public function registerInRouter(Router $router) {
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
     * @param string              $action
     * @param AppRequestInterface $request
     * @param mixed               ...$params
     * @return mixed
     * @throws NotFoundException
     * @throws \Reaction\Exceptions\InvalidConfigException
     * @throws \Reaction\Exceptions\NotInstantiableException
     * @throws \ReflectionException
     */
    public function resolveAction(AppRequestInterface $request, string $action, ...$params) {
        $action = $this->normalizeActionName($action);
        array_unshift($params, $request);
        $request->view->context = $this;
        return \Reaction::$di->invoke([$this, $action], $params);
    }

    /**
     * Resolve error
     * @param AppRequestInterface $request
     * @param \Throwable          $exception
     * @param bool                $asPlainText
     * @return ExtendedPromiseInterface|ResponseBuilderInterface
     */
    public function resolveError(AppRequestInterface $request, \Throwable $exception, $asPlainText = false)
    {
        $request->view->context = $this;
        if ($asPlainText) {
            return $this->resolveErrorAsPlainText($request, $exception);
        }
        $format = $request->response->format;
        switch ($format) {
            case Response::FORMAT_RAW:
            case Response::FORMAT_HTML:
                return $this->resolveErrorAsHtml($request, $exception);
            default:
                return $this->resolveErrorAsArray($request, $exception);
        }
    }

    /**
     * Render view
     * @param AppRequestInterface $request
     * @param string              $viewName
     * @param array               $params
     * @param bool                $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function render(AppRequestInterface $request, $viewName, $params = [], $asString = false) {
        return $this->renderInLayout($request, $viewName, $params, $asString);
    }

    /**
     * Render view partially
     * @param AppRequestInterface $request
     * @param string              $viewName
     * @param array               $params
     * @param bool                $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function renderPartial(AppRequestInterface $request, $viewName, $params = [], $asString = false)
    {
        return $this->renderInternal($request, $viewName, $params, false, $asString);
    }

    /**
     * Render for AJAX request
     * @param AppRequestInterface $request
     * @param string              $viewName
     * @param array               $params
     * @param bool                $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function renderAjax(AppRequestInterface $request, $viewName, $params = [], $asString = false)
    {
        return $this->renderInternal($request, $viewName, $params, true, $asString);
    }

    /**
     * Render view in layout
     * @param AppRequestInterface $request
     * @param string              $viewName
     * @param array               $params
     * @param bool                $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    protected function renderInLayout(AppRequestInterface $request, $viewName, $params = [], $asString = false)
    {
        $view = $request->view;
        if (isset($this->layout)) {
            $layoutFile = $view->findViewFile($this->layout, $this);
        } else {
            $layoutFile = $view->findViewFile($view->layout, $this);
        }
        $content = $this->renderInternal($request, $viewName, $params, false, true);
        $rendered = $view->renderFile($layoutFile, ['content' => $content], $this);
        if ($asString) {
            return $rendered;
        }
        $request->response->setBody($rendered);
        return $request->response;
    }

    /**
     * Render view internal function
     * @param AppRequestInterface $request
     * @param string              $viewName
     * @param array               $params
     * @param bool                $ajax
     * @param bool                $asString
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    protected function renderInternal(AppRequestInterface $request, $viewName, $params = [], $ajax = false, $asString = false) {
        $view = $request->view;
        $rendered = $ajax ? $view->renderAjax($viewName, $params, $this) : $view->render($viewName, $params, $this);
        if ($asString) {
            return $rendered;
        }
        $request->response->setBody($rendered);
        return $request->response;
    }

    /**
     * Normalize action name
     * @param string $action
     * @param bool   $throwException
     * @return string|null
     * @throws NotFoundException
     */
    protected function normalizeActionName($action, $throwException = true) {
        if (!StringHelper::startsWith($action, 'action')) {
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
     * @param AppRequestInterface $request
     * @param \Throwable          $exception
     * @return ResponseBuilderInterface|ExtendedPromiseInterface
     */
    protected function resolveErrorAsHtml(AppRequestInterface $request, \Throwable $exception)
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
            return $this->resolveErrorAsPlainText($request, $exception);
        }
        return \Reaction::$di->invoke([$this, $action], [$request, $exception]);
    }

    /**
     * Resolve error as array formatted
     * @param AppRequestInterface $request
     * @param \Throwable          $exception
     * @return ResponseBuilderInterface
     */
    protected function resolveErrorAsArray(AppRequestInterface $request, \Throwable $exception)
    {
        $data = $this->getErrorData($exception);
        $responseData = ['error' => $data];
        $request->response->setBody($responseData)->setStatusCodeByException($exception);
        return $request->response;
    }

    /**
     * Resolve error as pain text
     * @param AppRequestInterface $request
     * @param \Throwable          $exception
     * @return ResponseBuilderInterface
     */
    protected function resolveErrorAsPlainText(AppRequestInterface $request, \Throwable $exception)
    {
        $data = $this->getErrorData($exception);
        $responseData = ['error' => $data];
        $request->response->setBody(print_r($responseData, true))
            ->setStatusCodeByException($exception)
            ->setFormat(Response::FORMAT_RAW);
        return $request->response;
    }

    /**
     * Get error data as array
     * @param \Throwable $exception
     * @return array
     */
    protected function getErrorData(\Throwable $exception) {
        $data = [
            'message' => $exception->getMessage(),
            'code' => $exception instanceof HttpExceptionInterface ? $exception->statusCode : $exception->getCode(),
            'name' => $this->getExceptionName($exception),
        ];
        if (\Reaction::isDebug() || \Reaction::isDev()) {
            $data['trace'] = $exception->getTraceAsString();
        }
        return $data;
    }

    /**
     * Get name of exception
     * @param \Throwable $exception
     * @return string
     */
    protected function getExceptionName($exception) {
        if ($exception instanceof Exception) {
            return $exception->getName();
        } else {
            $classNameExp = explode('\\', get_class($exception));
            return end($classNameExp);
        }
    }
}