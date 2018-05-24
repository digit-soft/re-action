<?php

/** Default console config */
return [
    'container' => [
        'definitions' => [],
        'singletons' => [
            //Application
            'Reaction\StaticApplicationInterface' => 'Reaction\StaticApplicationConsole',
        ],
    ],
];