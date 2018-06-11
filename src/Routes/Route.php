<?php

namespace Reaction\Routes;

use Psr\Http\Message\ResponseInterface;
use Reaction;
use Reaction\Base\RequestAppComponent;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\Http\MethodNotAllowedException;
use Reaction\Exceptions\Http\NotFoundException;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\Promise;
use Reaction\Web\ResponseBuilderInterface;

/**
 * Class Route
 * @package Reaction\Routes
 */
class Route extends RequestAppComponent implements RouteInterface
{
    const CONTROLLER_RESOLVE_ACTION = 'resolveAction';
    const CONTROLLER_RESOLVE_ERROR  = 'resolveError';

    protected $_dispatchedData;
    protected $_controller;
    protected $_controllerMethod;
    protected $_action;
    protected $_params = [];
    protected $_paramsClean = [];
    protected $_routePath;

    /**
     * Get controller route path (With possible regex)
     * @param bool $onlyStaticPart
     * @return string|null
     */
    public function getRoutePath($onlyStaticPart = false) {
        if (!isset($this->_routePath) && isset($this->_controller)) {
            $this->_routePath = $this->controller->getActionPath($this->_action);
        }
        if ($onlyStaticPart) {
            return Reaction::$app->urlManager->extractStaticPart($this->_routePath);
        }
        return $this->_routePath;
    }

    /**
     * Get controller route params array
     * @return array
     */
    public function getRouteParams() {
        return $this->_paramsClean;
    }

    /**
     * Set data from dispatcher
     * @param array $data
     * @throws \Throwable
     */
    public function setDispatchedData($data = [])
    {
        $this->_dispatchedData = $data;
        $this->processDispatchedData();
    }

    /**
     * Get controller if applicable
     * @return Controller|null
     */
    public function getController()
    {
        if (!isset($this->_controller)) {
            $data = $this->_dispatchedData;
            if (isset($data[1]) && $data[1] instanceof ControllerInterface) {
                $this->_controller = $data[1];
            }
        }
        return $this->_controller;
    }

    /**
     * Get controller action if applicable
     * @return string
     */
    public function getAction()
    {
        if (!isset($this->_action)) {
            $data = $this->_dispatchedData;
            if (isset($data[2]) && is_string($data[2])) {
                $this->_action = $data[2];
            }
        }
        return $this->_action;
    }

    /**
     * Resolve route for request
     * @return ExtendedPromiseInterface
     */
    public function resolve()
    {
        $callable = [$this->_controller, $this->_controllerMethod];
        $args = $this->_params;
        array_unshift($args, $this->app, $this->_action);
        $promise = new Promise(function ($r) use ($callable, $args) {
            $result = call_user_func_array($callable, $args);
            $r($result);
        });
        return $promise->then(
            function ($response) {
                return $this->processResponse($response);
            }
        );
    }

    /**
     * Parse data from dispatcher
     * @throws \Throwable
     */
    protected function processDispatchedData() {
        list($code, $controller, $action, $params) = $this->_dispatchedData;

        if ($code === RouterInterface::ERROR_OK) {
            $this->_controllerMethod = static::CONTROLLER_RESOLVE_ACTION;
            $this->_controller = $controller;
            $this->_action = $action;
            $this->_params = $this->_paramsClean = $params;
            //Overwrite query parameters
            if (ArrayHelper::isAssociative($this->_params)) {
                $queryParams = ArrayHelper::merge($this->app->reqHelper->getQueryParams(), $this->_params);
                $this->app->reqHelper->setQueryParams($queryParams);
            }
        } else {
            $exception = $this->getRouterException($code);
            throw $exception;
        }
    }

    /**
     * Process response from controller action
     * @param ResponseInterface|ResponseBuilderInterface $response
     * @return ResponseInterface
     */
    protected function processResponse($response) {
        if ($response instanceof ResponseInterface) {
            return $response;
        }
        if ($response instanceof ResponseBuilderInterface) {
            return $response->build();
        }
        //Do not throw error on console app
        if (Reaction::isConsoleApp()) {
            return $response;
        }
        $type = is_object($response) ? get_class($response) : gettype($response);
        $message = sprintf('Return value must be instance of "%s", but "%s" given', ResponseInterface::class, $type);
        throw new InvalidArgumentException($message);
    }

    /**
     * Get Exception by Router error code
     * @param int $code
     * @return \Throwable
     */
    protected function getRouterException($code) {
        $errors = [
            RouterInterface::ERROR_NOT_FOUND => NotFoundException::class,
            RouterInterface::ERROR_METHOD_NOT_ALLOWED => MethodNotAllowedException::class,
            '*' => NotSupportedException::class,
        ];
        $errorClass = isset($errors[$code]) ? $errors[$code] : $errors['*'];
        try {
            $exception = Reaction::create($errorClass);
        } catch (InvalidConfigException $createException) {
            return $createException;
        }
        return $exception;
    }
}