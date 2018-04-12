<?php
/** Default config */
return [
    //Application config
    'app.config' => [
        'charset' => 'utf-8',
        'hostname' => '127.0.0.1',
        'port' => 4000,
        //Initial app aliases
        'aliases' => [
            '@root' => getcwd(),
            '@runtime' => '@root/runtime',
            '@reaction' => dirname(__FILE__),
        ],
        //Components
        'components' => [
            'router' => 'app.router',
            'logger' => 'app.logger',
        ],
    ],
    //Dependency injection config
    'di.config' => [
        'useAnnotations' => false,
        'useAutowiring' => true,
    ],

    /** Class definitions */
    //Loop
    \React\EventLoop\LoopInterface::class => \DI\factory(function ($di) {
        return \React\EventLoop\Factory::create();
    })->scope(\DI\Scope::SINGLETON),
    //Socket server
    \React\Socket\ServerInterface::class => \DI\factory(function (\DI\Container $di = null) {
        $appConf = $di->get('app.config');
        $socketUri = $appConf['hostname'] . ':' . $appConf['port'];
        return $di->make(\React\Socket\Server::class, [ 'uri' => $socketUri, 'loop' => \DI\get(\React\EventLoop\LoopInterface::class) ]);
    })->scope(\DI\Scope::SINGLETON),
    //Router
    \Reaction\Routes\RouterInterface::class => \DI\get(\Reaction\Routes\Router::class),
    \Reaction\Routes\Router::class => \DI\create()->scope(\DI\Scope::SINGLETON),
    //Stdout writable stream
    'stdoutWriteStream' => \DI\create(\React\Stream\WritableResourceStream::class)
        ->constructor(STDOUT, \DI\get(\React\EventLoop\LoopInterface::class))
        ->scope(\DI\Scope::SINGLETON),
    'stdoutLogger' => \DI\create(\Reaction\Base\Logger\StdioLogger::class)
        ->constructor(\DI\get('stdoutWriteStream'), \DI\get(\React\EventLoop\LoopInterface::class))
        ->scope(\DI\Scope::SINGLETON),

    /** Aliases for DI */
    'app.router' => \DI\get(\Reaction\Routes\RouterInterface::class),
    'app.logger' => \DI\get('stdoutLogger'),

    //Place for custom instances config

];