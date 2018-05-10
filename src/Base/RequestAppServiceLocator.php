<?php

namespace Reaction\Base;

use Reaction\DI\ServiceLocator;
use Reaction\RequestApplicationInterface;

/**
 * Class RequestAppServiceLocator
 * @package Reaction\Base
 */
class RequestAppServiceLocator extends ServiceLocator
{
    /** @var RequestApplicationInterface An request application instance reference */
    public $app;
}