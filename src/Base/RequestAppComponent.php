<?php

namespace Reaction\Base;

use Reaction\RequestApplicationInterface;

/**
 * Class RequestAppComponent
 * @package Reaction\Base
 */
class RequestAppComponent extends Component
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