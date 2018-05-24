<?php

namespace Reaction;

use React\Http\Server as Http;
use React\Socket\ServerInterface as SocketServerInterface;
use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Class StaticApplication
 * @package Reaction
 */
class StaticApplication extends StaticApplicationAbstract
{
    /** @var ExtendedPromiseInterface */
    public $initPromise;
    /** @var bool */
    public $initialized = false;

    /**
     * Run application
     * @throws Exceptions\InvalidConfigException
     */
    public function run() {
        // TODO: Make more serious Exception handler :)
        $this->router->registerControllers();
        $this->router->publishRoutes();
        $this->addMiddleware([$this->router, 'resolveRequest']);
        $this->socket = \Reaction::create(SocketServerInterface::class);
        $this->http = \Reaction::create(Http::class, [$this->middleware]);
        $this->http->listen($this->socket);
        //Exception handler
        $this->http->on('error', function (\Throwable $error) {
            $this->logger->alert($error);
        });
        parent::run();
    }
}