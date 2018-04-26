<?php

namespace Reaction\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Reaction\Base\Configurable;
use Reaction\Helpers\ArrayHelper;

/**
 * Class RequestReplacer. Replaces react request for an AppRequestInterface instance
 * @package Reaction\Middleware
 */
class RequestReplacer implements Configurable
{
    /** @var string New Request class name */
    public $requestClassName = 'Reaction\Web\AppRequestInterface';

    /**
     * RequestReplacer constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        \Reaction::configure($this, $config);
    }

    /**
     * Middleware callback
     * @param ServerRequestInterface $request
     * @param callable $next
     * @return mixed
     * @throws \Reaction\Exceptions\InvalidConfigException
     * @throws \ReflectionException
     */
    public function __invoke(ServerRequestInterface $request, $next)
    {
        $config = \Reaction::$config->get('request', []);
        if (is_string($this->requestClassName)) {
            $config['class'] = $this->requestClassName;
        } elseif (is_array($this->requestClassName)) {
            $config = ArrayHelper::merge($this->requestClassName, $config);
        }
        $config['reactRequest'] = $request;
        $newRequest = \Reaction::create($config);

        return $next($newRequest);
    }
}