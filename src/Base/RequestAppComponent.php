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
}