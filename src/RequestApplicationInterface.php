<?php

namespace Reaction;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
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
     */
    public function loadComponents();
}