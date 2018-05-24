<?php

namespace Reaction;

use React\Http\Io\ServerRequest;
use function Reaction\Promise\resolve;
use Reaction\Web\Response;

/**
 * Class StaticApplicationConsole
 * @package Reaction
 */
class StaticApplicationConsole extends StaticApplicationAbstract
{
    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->loop->futureTick([$this, 'runConsoleRequest']);
        parent::run();
    }

    /**
     * Emulates request resolving in console
     */
    public function runConsoleRequest() {
        $promise = resolve(true);
        $promise->then(
            function() {
                return $this->router->resolveRequest($this->createRequest());
            }
        )->then(
            function($response = null) {
                if ($response instanceof Response) {
                    $body = $response->getBody();
                    if (is_object($body) && method_exists($body, '__toString')) {
                        $body = (string)$body;
                    }
                    return $body;
                }
                return $response;
            }
        )->done(function($result = null) {
            if (is_string($result)) {
                $this->logger->debug($result);
            }
            $this->loop->stop();
        }, function($error) {
            \Reaction::error($error);
            $this->loop->stop();
        });
    }

    /**
     * @return ServerRequest
     */
    protected function createRequest() {
        //\Reaction::warning($_SERVER);
        $serverParams = $_SERVER;
        $fileName = ltrim($serverParams['SCRIPT_NAME'], './');
        $serverParams['DOCUMENT_ROOT'] = getcwd();
        $serverParams['SCRIPT_NAME'] = $serverParams['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $fileName;
        $serverParams['REQUEST_METHOD'] = 'GET';
        $request = new ServerRequest('GET', '/', [], null, '1.1', $serverParams);
        return $request;
    }
}