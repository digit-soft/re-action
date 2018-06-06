<?php

/** @var Composer\Autoload\ClassLoader $loader */
$loader = @include __DIR__ . '/../vendor/autoload.php';
if (!$loader) {
    $loader = require __DIR__ . '/../../../../vendor/autoload.php';
}
$loader->addPsr4('Reaction\\Tests\\Framework\\', __DIR__);