<?php

namespace Reaction\Routes;

use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Reaction\Base\BaseObject;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\Http\MethodNotAllowedException;
use Reaction\Exceptions\Http\NotFoundException;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\Promise;
use Reaction\Web\AppRequestInterface;
use Reaction\Web\ResponseBuilderInterface;

/**
 * Class Route
 * @package Reaction\Routes
 */
class Route extends BaseObject implements RouteInterface
{
    /** @var AppRequestInterface */
    public $request;

    protected $_dispatchedData;
    protected $_controller;
    protected $_action;
    protected $_actionClean;
    protected $_params = [];
    protected $_paramsClean = [];
    protected $_exception;
    protected $_exceptionsCount = 0;
    protected $_routePath;

    /**
     * Get controller route path (With possible regex)
     * @param bool $onlyStaticPart
     * @return string|null
     */
    public function getRoutePath($onlyStaticPart = false) {
        if (!isset($this->_routePath) && isset($this->_controller)) {
            $this->_routePath = $this->controller->getActionPath($this->_actionClean);
        }
        if ($onlyStaticPart) {
            return \Reaction::$app->urlManager->extractStaticPart($this->_routePath);
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
            if (isset($data[1]) && is_array($data[1]) && count($data[1]) >= 2 && $data[1][0] instanceof ControllerInterface) {
                $this->_controller = $data[1][0];
            }
        }
        return $this->_controller;
    }

    /**
     * Check that route has error
     * @return bool
     */
    public function getIsError()
    {
        return empty($this->_exception);
    }

    /**
     * Get exception if exists
     * @return \Throwable
     */
    public function getException()
    {
        return $this->_exception;
    }

    /**
     * Set exception
     * @param \Throwable|mixed $exception
     */
    public function setException($exception) {
        if (!($exception instanceof \Throwable)) {
            if (is_string($exception)) {
                $exception = new Exception($exception);
            } else {
                $type = is_object($exception) ? get_class($exception) : gettype($exception);
                $exception = new NotSupportedException(sprintf('Only exceptions are supported in "%s" but "%s" given', __METHOD__, $type));
            }
        }
        $this->_exceptionsCount++;
        $this->_exception = $exception;
    }

    /**
     * Convert route to error route
     * @param \Throwable $exception
     */
    public function convertToError(\Throwable $exception) {
        $this->setException($exception);
        $this->_controller = \Reaction::$app->router->errorController;
        $this->_action = 'resolveError';
        $this->_params = [$this->_exception];
        //If we have cycle of exceptions than deliver error as plain text
        if ($this->_exceptionsCount > 3) {
            $this->_params[] = true;
        }
    }

    /**
     * Resolve route for request
     * @param AppRequestInterface $request
     * @return ExtendedPromiseInterface
     */
    public function resolve(AppRequestInterface $request)
    {
        $callable = isset($this->_controller) ? [$this->_controller, $this->_action] : $this->_action;
        $args = $this->_params;
        array_unshift($args, $request);
        $promise = new Promise(function ($r) use ($callable, $args) {
            $result = call_user_func_array($callable, $args);
            $r($result);
        });
        $self = $this;
        return $promise->then(
            function ($response) use ($self) {
                return $self->processResponse($response);
            }
        )->otherwise(
            function ($error) use ($self, &$request) {
                $self->convertToError($error);
                return $self->resolve($request);
            }
        );
    }

    /**
     * Parse data from dispatcher
     */
    protected function processDispatchedData() {
        $data = $this->_dispatchedData;
        $dispatcherCode = $data[0];

        if ($dispatcherCode === Dispatcher::FOUND) {
            $callable = $data[1];
            //Parse params
            if (isset($data[2])) {
                $this->_params = is_array($data[2]) ? $data[2] : (array)$data[2];
                $this->_paramsClean = $this->_params;
                $queryParams = ArrayHelper::merge($this->request->_getQueryParams(), $this->_params);
                $this->request->setQueryParams($queryParams);
            }
            //Parse controller and action
            if (is_array($callable) && count($callable) >= 2 && $callable[0] instanceof ControllerInterface) {
                $this->_controller = $callable[0];
                $this->_action = 'resolveAction';
                $this->_actionClean = $callable[1];
                array_unshift($this->_params, $callable[1]);
            } else {
                $this->_action = $callable;
            }
        } else {
            $exception = $this->getDispatcherException($dispatcherCode);
            $this->convertToError($exception);
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
        $type = is_object($response) ? get_class($response) : gettype($response);
        $message = sprintf('Return value must be instance of "%s", but "%s" given', ResponseInterface::class, $type);
        throw new InvalidArgumentException($message);
    }

    /**
     * Get Exception by dispatcher code
     * @param $dispatcherCode
     * @return \Throwable
     */
    protected function getDispatcherException($dispatcherCode) {
        $errors = [
            Dispatcher::NOT_FOUND => NotFoundException::class,
            Dispatcher::METHOD_NOT_ALLOWED => MethodNotAllowedException::class,
            '*' => NotSupportedException::class,
        ];
        $errorClass = isset($errors[$dispatcherCode]) ? $errors[$dispatcherCode] : $errors['*'];
        try {
            $exception = \Reaction::create($errorClass);
        } catch (InvalidConfigException $createException) {
            return $createException;
        }
        return $exception;
    }
}