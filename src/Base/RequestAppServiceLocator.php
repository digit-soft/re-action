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

    /**
     * RequestAppComponent constructor.
     * @param RequestApplicationInterface $app
     * @param array                       $config
     */
    public function __construct(RequestApplicationInterface $app, array $config = [])
    {
        $this->app = $app;
        parent::__construct($config);
    }
}