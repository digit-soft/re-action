<?php
/** Default config */
return [
    //Application config
    'app.config' => [
        'charset' => new \Reaction\Helpers\IgnoreArrayValue('utf-8'),
        'hostname' => new \Reaction\Helpers\IgnoreArrayValue('127.0.0.1'),
        'port' => new \Reaction\Helpers\IgnoreArrayValue(4000),
        //Initial app aliases
        'aliases' => [
            '@root' => new \Reaction\Helpers\IgnoreArrayValue(getcwd()),
            '@runtime' => new \Reaction\Helpers\IgnoreArrayValue('@root/runtime'),
            '@reaction' => new \Reaction\Helpers\IgnoreArrayValue(dirname(__FILE__)),
        ],
        //Components
        'components' => [
            'router' => new \Reaction\Helpers\IgnoreArrayValue('app.router'),
        ],
    ],
    //Dependency injection config
    'di.config' => [
        'useAnnotations' => new \Reaction\Helpers\IgnoreArrayValue(false),
        'useAutowiring' => new \Reaction\Helpers\IgnoreArrayValue(true),
    ],

    //Class definitions
    \Reaction\Routes\RouterInterface::class => \DI\get(\Reaction\Routes\Router::class),
    \Reaction\Routes\Router::class => \DI\create()->scope(\DI\Scope::SINGLETON),

    //Aliases for DI
    'app.router' => \DI\get(\Reaction\Routes\RouterInterface::class),

    //Place for custom instances config

];