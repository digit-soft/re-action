<?php

use Reaction\DI\Definition;
use Reaction\DI\Instance;

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
            '@views' => '@root/Views',
            '@reaction' => dirname(__FILE__),
        ],
        //Components
        'components' => [
            'router' => 'Reaction\Routes\RouterInterface',
            'logger' => 'stdioLogger',
        ],
    ],
    //DI definitions
    'container' => [
        'definitions' => [

        ],
        'singletons' => [
            //React event loop
            'React\EventLoop\LoopInterface' => function() { return \React\EventLoop\Factory::create(); },
            //React socket server
            'React\Socket\ServerInterface' => [
                ['class' => \React\Socket\Server::class],
                ['0.0.0.0:4000', Instance::of(\React\EventLoop\LoopInterface::class)],
            ],
            //React http server
            \React\Http\Server::class => [
                'class' => \React\Http\Server::class,
            ],
            //Application
            'Reaction\BaseApplicationInterface' => \Reaction\BaseApplication::class,
            //'Reaction\BaseApplication' => \Reaction\BaseApplication::class,
            //Router
            'Reaction\Routes\RouterInterface' => \Reaction\Routes\Router::class,
            //'Reaction\Routes\Router' => \DI\create()->scope(\DI\Scope::SINGLETON),
            //Stdout writable stream
            'stdoutWriteStream' => Definition::of(\React\Stream\WritableResourceStream::class)
                ->withParams([STDOUT, Instance::of(\React\EventLoop\LoopInterface::class)]),
            //Stdio logger
            'stdioLogger' => Definition::of(\Reaction\Base\Logger\StdioLogger::class)
                ->withParams([Instance::of('stdoutWriteStream'), Instance::of(\React\EventLoop\LoopInterface::class)])
                ->withConfig(['withLineNum' => true]),
        ],
    ],

];