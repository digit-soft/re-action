<?php

namespace Reaction;

use DI\DependencyException;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\Http\Server as Http;
use React\Socket\Server as Socket;
use React\Socket\ServerInterface as SocketServerInterface;
use Reaction\Base\Component;

/**
 * Class BaseApplication
 * @package Reaction
 */
class BaseApplication extends Component implements BaseApplicationInterface
{
    /** @var string */
    public $charset = 'UTF-8';
    /** @var string */
    public $hostname;
    /** @var integer */
    public $port;
    /** @var LoopInterface */
    public $loop;
    /** @var Http */
    public $http;
    /** @var Socket */
    public $socket;

    protected $middleware = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->loop = \Reaction::create(\React\EventLoop\LoopInterface::class);
    }

    /**
     * Run application
     * @throws DependencyException
     * @throws Exceptions\InvalidConfigException
     * @throws \DI\NotFoundException
     */
    public function run() {
        $this->middleware[] = function (RequestInterface $request) { return new Response(200, [], "test!\n" . time()); };
        $this->socket = \Reaction::create(SocketServerInterface::class);
        $this->http = \Reaction::create(Http::class, ['requestHandler' => $this->middleware]);
        \Reaction::$di->set(Http::class, $this->http);
        $this->http->listen($this->socket);
        echo "Running server on $this->hostname:$this->port\n";
        $this->loop->run();
    }
}