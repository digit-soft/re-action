<?php

namespace Reaction\Routes;

use Reaction\Base\RequestAppComponentInterface;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\RequestApplicationInterface;

/**
 * Interface RouteInterface
 * @package Reaction\Routes
 * @property RequestApplicationInterface $app
 * @property Controller                  $controller
 * @property bool                        $isError
 * @property \Throwable                  $exception
 */
interface RouteInterface extends RequestAppComponentInterface
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
     * @param bool $onlyStaticPart
     * @return string|null
     */
    public function getRoutePath($onlyStaticPart = false);

    /**
     * Get controller route params array
     * @return array
     */
    public function getRouteParams();

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
     * @return ExtendedPromiseInterface
     */
    public function resolve();
}