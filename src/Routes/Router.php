<?php

namespace Reaction\Routes;

use FastRoute\Dispatcher;
use FastRoute\RouteParser;
use Reaction;
use Reaction\Helpers\ArrayHelper;
use Reaction\RequestApplicationInterface;

/**
 * Class Router
 * @package Reaction\Routes
 * @property RouteParser $routeParser
 */
class Router extends RouterAbstract implements RouterInterface
{
    public $dispatcherClass = '\FastRoute\simpleDispatcher';
    public $dispatcherOptions = [];
    public $routeParserClass = '\FastRoute\RouteParser\Std';
    public $routeParserOptions = [];

    /**
     * @var RouteParser
     */
    protected $_routeParser;

    /**
     * Search for a given route
     * @param RequestApplicationInterface $app
     * @param string                      $routePath
     * @param string                      $method
     * @return array
     */
    public function searchRoute(RequestApplicationInterface $app, $routePath, $method = 'GET')
    {
        $dispatcherData = $this->dispatcher->dispatch($method, $routePath);
        if ($dispatcherData[0] === Dispatcher::NOT_FOUND) {
            return [static::ERROR_NOT_FOUND, null, null, []];
        } elseif ($dispatcherData[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return [static::ERROR_METHOD_NOT_ALLOWED, null, null, []];
        } else {
            $ctrlAndAction = $dispatcherData[1];
            $params = isset($dispatcherData[2]) ? $dispatcherData[2] : [];
            $params = (array)$params;
            return [static::ERROR_OK, $ctrlAndAction[0], $ctrlAndAction[1], $params];
        }
    }

    /**
     * @inheritdoc
     */
    public function initRoutes()
    {
        parent::initRoutes();
        $this->publishRoutes();
    }

    /**
     * Create route dispatcher
     * @param callable|string|array $callback
     * @return mixed
     */
    protected function createDispatcher($callback) {
        $function = $this->dispatcherClass;
        return $function($callback, $this->dispatcherOptions);
    }

    /**
     * Getter for $routeParser
     * @return RouteParser
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    protected function getRouteParser() {
        if (!isset($this->_routeParser)) {
            $this->_routeParser = Reaction::create($this->routeParserClass, $this->routeParserOptions);
        }
        return $this->_routeParser;
    }

    /**
     * Register all defined routes in dispatcher
     */
    protected function publishRoutes() {
        $routes = $this->routes;
        $this->parseRoutesData();
        $this->dispatcher = $this->createDispatcher(function (\FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $routeRow) {
                $r->addRoute($routeRow['httpMethod'], $routeRow['route'], $routeRow['handler']);
            }
        });
    }

    /**
     * Parse routes data to obtain path expressions, those used for URL building
     */
    protected function parseRoutesData() {
        $routes = $this->routes;
        foreach ($routes as $routeData) {
            $path = $routeData['route'];
            $this->buildPathExpressions($path, true);
        }
        foreach ($this->_routePaths as &$routePaths) {
            $prevCnt = 0;
            ArrayHelper::multisort($routePaths, function ($row) use (&$prevCnt) {
                return count($row['params']);
            }, SORT_DESC);
        }
    }

    /**
     * Build path expression from router path
     * @param string $path
     * @param bool $store
     * @return array
     */
    protected function buildPathExpressions($path, $store = false) {
        $segments = $this->routeParser->parse($path);
        $expressions = [];
        foreach ($segments as $segmentGroup) {
            $expression = '';
            $params = [];
            $staticPart = '/';
            $staticPartEnded = false;
            foreach ($segmentGroup as $segment) {
                if (is_string($segment)) {
                    $expression .= $segment;
                    if (!$staticPartEnded) {
                        $staticPart .= $segment;
                    }
                } elseif (is_array($segment)) {
                    $staticPartEnded = true;
                    $expression .= '{'.$segment[0].'}';
                    $params[] = $segment[0];
                }
            }
            $staticPart = '/' . trim($staticPart, '/');
            if ($store && !empty($params)) {
                $row = [
                    'exp' => $expression,
                    'params' => $params,
                ];
                $this->_routePaths[$staticPart] = isset($this->_routePaths[$staticPart]) ? $this->_routePaths[$staticPart] : [];
                $this->_routePaths[$staticPart][] = $row;
            }
            $expressions[$expression] = [
                'expression' => $expression,
                'prefix' => $staticPart,
                'params' => $params,
            ];
        }

        return $expressions;
    }
}