<?php
/** Default config */
return [
    //Application config
    'app' => [
        'charset' => 'utf-8',
        'hostname' => '127.0.0.1',
        'port' => 4000,
        //Initial app aliases
        'aliases' => [
            '@root' => getcwd(),
            '@runtime' => '@root/Runtime',
            '@reaction' => dirname(__FILE__),
        ],
        //Components
        'components' => [
            'router' => 'app.router',
            'logger' => 'app.logger',
        ],
    ],
    //DI definitions
    'container' => [
        /** Class definitions */
        //Loop
        'React\EventLoop\LoopInterface' => \DI\factory(function ($di) {
            return \React\EventLoop\Factory::create();
        })->scope(\DI\Scope::SINGLETON),
        //Socket server
        'React\Socket\ServerInterface' => \DI\factory(function (\DI\Container $di = null) {
            $appConf = Reaction::$config->get('app');
            $socketUri = $appConf['hostname'] . ':' . $appConf['port'];
            return $di->make(\React\Socket\Server::class, [ 'uri' => $socketUri, 'loop' => \DI\get(\React\EventLoop\LoopInterface::class) ]);
        })->scope(\DI\Scope::SINGLETON),
        //Router
        'Reaction\Routes\RouterInterface' => \DI\get(\Reaction\Routes\Router::class),
        'Reaction\Routes\Router' => \DI\create()->scope(\DI\Scope::SINGLETON),
        //Stdout writable stream
        'stdoutWriteStream' => \DI\create(\React\Stream\WritableResourceStream::class)
            ->constructor(STDOUT, \DI\get(\React\EventLoop\LoopInterface::class))
            ->scope(\DI\Scope::SINGLETON),
        //Stdio logger
        'stdioLogger' => \DI\create(\Reaction\Base\Logger\StdioLogger::class)
            ->constructor(\DI\get('stdoutWriteStream'), \DI\get(\React\EventLoop\LoopInterface::class))
            ->property('withLineNum', true)
            ->scope(\DI\Scope::SINGLETON),

        /** Aliases for DI */
        'app.router' => \DI\get(\Reaction\Routes\RouterInterface::class),
        'app.logger' => \DI\get('stdioLogger'),
    ],
    //DI config
    'container.config' => [
        'useAnnotations' => false,
        'useAutowiring' => true,
    ],

];