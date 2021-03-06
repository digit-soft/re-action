<?php
/**
 * Default web config
 */

use Reaction\DI\Instance;
use Reaction\DI\Value;

return [
    //Static application config
    'appStatic' => [
        //Components
        'components' => [
            'router' => [
                'class' => 'Reaction\Routes\RouterInterface',
                'controllerNamespaces' => [
                    'Reaction\Routes\StdControllers',
                    'App\Controllers',
                ],
            ],
        ]
    ],
    'container' => [
        'definitions' => [],
        'singletons' => [
            //Application
            'Reaction\StaticApplicationInterface' => 'Reaction\StaticApplicationWeb',
            //React http server
            'React\Http\Server' => 'React\Http\Server',
            //Error handler
            'Reaction\Base\ErrorHandlerInterface' => 'Reaction\Web\ErrorHandler',
            //React socket server
            'React\Socket\Server' => [
                ['class' => 'React\Socket\Server'],
                [
                    Value::of(function() {
                        return '0.0.0.0:' . Reaction::$config->get('appStatic.port');
                    }),
                    Instance::of(\React\EventLoop\LoopInterface::class),
                ],
            ],
        ],
    ],
];