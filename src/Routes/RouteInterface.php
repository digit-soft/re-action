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
 * @property string                      $action
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
     * Get controller action if applicable
     * @return string
     */
    public function getAction();

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
     * Resolve route for request
     * @return ExtendedPromiseInterface
     */
    public function resolve();
}