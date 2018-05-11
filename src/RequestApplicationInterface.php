<?php

namespace Reaction;

use Psr\Http\Message\ServerRequestInterface;
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
 * @property ResponseBuilderInterface $response     //TODO: Attach response builder
 * @property Session                  $session      //TODO: Attach session
 * @property User                     $user         //TODO: Attach web user
 * @property UrlManager               $urlManager   //TODO: Attach session
 * @property View                     $view         //TODO: Attach view
 * @property AssetManager             $assetManager //TODO: Attach assetManager
 * @property HelpersGroup             $helpers
 */
interface RequestApplicationInterface extends EventEmitterWildcardInterface
{
    const EVENT_REQUEST_INIT    = 'requestInit';
    const EVENT_REQUEST_END     = 'requestEnd';

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
     * Get app URL manager
     * @return \Reaction\Web\UrlManager
     */
    public function getUrlManager();

    /**
     * Get app home URL
     * @return string
     */
    public function getHomeUrl();
}