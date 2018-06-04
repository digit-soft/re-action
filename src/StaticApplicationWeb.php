<?php

namespace Reaction;

use React\Http\Server as Http;
use React\Socket\ServerInterface as SocketServerInterface;
use Reaction;

/**
 * Class StaticApplication
 * @package Reaction
 */
class StaticApplicationWeb extends StaticApplicationAbstract
{
    /**
     * Run application
     * @throws Exceptions\InvalidConfigException
     */
    public function run() {
        $this->router->initRoutes();
        $this->addMiddleware([$this, 'processRequest']);
        $this->socket = Reaction::create(SocketServerInterface::class);
        $this->http = Reaction::create(Http::class, [$this->middleware]);
        $this->http->listen($this->socket);
        //Exception handler
        $this->http->on('error', function (\Throwable $error) {
            $this->logger->alert($error);
        });
        parent::run();
    }
}