<?php
/** Default config */
return [
    //Application config
    'app.config' => [
        'charset' => new \Reaction\Helpers\IgnoreArrayValue('utf-8'),
        'hostname' => new \Reaction\Helpers\IgnoreArrayValue('127.0.0.1'),
        'port' => new \Reaction\Helpers\IgnoreArrayValue(4000),
    ],
    //Dependency injection config
    'di.config' => [
        'useAnnotations' => new \Reaction\Helpers\IgnoreArrayValue(true),
        'useAutowiring' => new \Reaction\Helpers\IgnoreArrayValue(false),
    ],

    //Place for custom instances config

];