<?php

namespace Reaction;

use React\EventLoop\LoopInterface;
use React\Http\Server as Http;
use React\Socket\Server as Socket;
use React\Socket\ServerInterface;
use Reaction\Base\Component;

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
     */
    public function run() {
        $this->socket = \Reaction::create(\React\Socket\ServerInterface::class);
        $this->middleware[] = function () {  };
        $this->http = \Reaction::create(\React\Http\Server::class, ['requestHandler' => $this->middleware]);
        //print_r($this->http);
        //$this->http = new Http($this->middleware);
        $this->loop->run();
    }
}