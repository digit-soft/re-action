<?php

namespace Reaction\Routes;

use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Web\AppRequestInterface;

/**
 * Interface RouteInterface
 * @package Reaction\Routes
 * @property AppRequestInterface $request
 * @property Controller          $controller
 * @property bool                $isError
 * @property \Throwable          $exception
 */
interface RouteInterface
{
    /**
     * Set data from dispatcher
     * @param array $data
     */
    public function setDispatchedData($data = []);

    /**
     * Get controller if applicable
     * @return Controller|null
     */
    public function getController();

    /**
     * Get controller route path (With possible regex)
     * @return string|null
     */
    public function getRoutePath();

    /**
     * Check that route has error
     * @return bool
     */
    public function getIsError();

    /**
     * Get exception if exists
     * @return \Throwable
     */
    public function getException();

    /**
     * Set exception
     * @param \Throwable|mixed $exception
     */
    public function setException($exception);

    /**
     * Resolve route for request
     * @param AppRequestInterface $request
     * @return ExtendedPromiseInterface
     */
    public function resolve(AppRequestInterface $request);
}