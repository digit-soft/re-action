<?php

namespace Reaction;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Reaction\Base\ErrorHandler;
use Reaction\DI\ServiceLocator;
use Reaction\Events\EventEmitterWildcardInterface;
use Reaction\Helpers\Request\HelpersGroup;
use Reaction\Routes\RouteInterface;
use Reaction\Web\AssetManager;
use Reaction\Web\RequestHelper;
use Reaction\Web\ResponseBuilderInterface;
use Reaction\Web\Sessions\Session;
use Reaction\Web\UrlManager;
use Reaction\Web\User;
use Reaction\Web\View;

/**
 * Interface RequestApplicationInterface
 * @package Reaction
 * @property string                   $charset
 * @property string                   $language
 * @property string                   $homeUrl
 * @property ServerRequestInterface   $request
 * @property RequestHelper            $reqHelper
 * @property RouteInterface           $route
 * @property ResponseBuilderInterface $response
 * @property Session                  $session
 * @property User                     $user
 * @property UrlManager               $urlManager
 * @property View                     $view
 * @property AssetManager             $assetManager
 * @property HelpersGroup             $helpers
 * @property ErrorHandler             $errorHandler
 */
interface RequestApplicationInterface extends EventEmitterWildcardInterface
{
    const EVENT_REQUEST_INIT    = 'requestInit';
    const EVENT_REQUEST_END     = 'requestEnd';

    /**
     * Resolve app action
     * @param string|null $routePath
     * @param string|null $method
     * @param array|null $params
     * @return Promise\ExtendedPromiseInterface
     */
    public function resolveAction($routePath = null, $method = null, $params = null);

    /**
     * Get route for request
     * @return RouteInterface
     */
    public function getRoute();

    /**
     * Setter for route
     * @param RouteInterface|null $route
     */
    public function setRoute($route = null);

    /**
     * Create Route with given params
     * @param string|null $routePath
     * @param string|null $method
     * @param array|null  $params
     * @param bool        $onlyReturn
     * @return RouteInterface
     */
    public function createRoute($routePath = null, $method = null, $params = null, $onlyReturn = false);

    /**
     * Get app URL manager
     * @return \Reaction\Web\UrlManager
     */
    public function getUrlManager();

    /**
     * Get app home URL
     * @return string
     */
    public function getHomeUrl();

    /**
     * Load components after init (Function is called manually).
     * Returns a promise which will be resolved after all components are initialized
     * if components implementing ComponentInitBlockingInterface or immediately if not.
     * @return PromiseInterface
     * @see ServiceLocator::loadComponents()
     */
    public function loadComponents();

    /**
     * Returns a value indicating whether the locator has the specified component definition or has instantiated the component.
     * This method may return different results depending on the value of `$checkInstance`.
     *
     * - If `$checkInstance` is false (default), the method will return a value indicating whether the locator has the specified
     *   component definition.
     * - If `$checkInstance` is true, the method will return a value indicating whether the locator has
     *   instantiated the specified component.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool $checkInstance whether the method should check if the component is shared and instantiated.
     * @return bool whether the locator has the specified component definition or has instantiated the component.
     * @see ServiceLocator::has()
     */
    public function has($id, $checkInstance = false);

    /**
     * Returns the component instance with the specified ID.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool   $throwException whether to throw an exception if `$id` is not registered with the locator before.
     * @return \object|null the component of the specified ID. If `$throwException` is false and `$id`
     * is not registered before, null will be returned.
     * @see ServiceLocator::get()
     */
    public function get($id, $throwException = true);

    /**
     * Registers a component definition with this locator.
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * @param string $id component ID (e.g. `db`).
     * @param mixed $definition the component definition to be registered with this locator.
     * It can be one of the following:
     *
     * - a class name
     * - a configuration array: the array contains name-value pairs that will be used to
     *   initialize the property values of the newly created object when [[get()]] is called.
     *   The `class` element is required and stands for the the class of the object to be created.
     * - a PHP callable: either an anonymous function or an array representing a class method (e.g. `['Foo', 'bar']`).
     *   The callable will be called by [[get()]] to return an object associated with the specified component ID.
     * - an object: When [[get()]] is called, this object will be returned.
     *
     * @see ServiceLocator::set()
     * @see RequestApplication::set()
     */
    public function set($id, $definition);
}