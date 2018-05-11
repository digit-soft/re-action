<?php

namespace Reaction\Base;

use Reaction\RequestApplicationInterface;

/**
 * Class RequestAppComponent
 * @package Reaction\Base
 */
class RequestAppComponent extends Component implements RequestAppComponentInterface
{
    /** @var RequestApplicationInterface An request application instance reference */
    public $app;
}