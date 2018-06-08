<?php

/** Default console config */
return [
    //Static application config
    'appStatic' => [
        'debug' => true,
        //Components
        'components' => [],
    ],
    //Request application config
    'appRequest' => [
        'components' => [
            'reqHelper' => [
                'class' => 'Reaction\Console\Web\RequestHelper',
            ],
        ],
    ],
    'container' => [
        'definitions' => [],
        'singletons' => [
            //Application
            'Reaction\StaticApplicationInterface' => 'Reaction\StaticApplicationConsole',
            //Router
            'Reaction\Routes\RouterInterface' => 'Reaction\Console\Routes\Router',
            //Error handler
            'Reaction\Base\ErrorHandlerInterface' => 'Reaction\Console\ErrorHandler',
        ],
    ],
];