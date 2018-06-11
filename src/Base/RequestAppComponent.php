<?php

namespace Reaction\Base;

use Reaction\Helpers\Request\HelpersGroup;
use Reaction\RequestApplicationInterface;

/**
 * Class RequestAppComponent
 * @package Reaction\Base
 * @property HelpersGroup $hlp
 */
class RequestAppComponent extends Component implements RequestAppComponentInterface
{
    /** @var RequestApplicationInterface An request application instance reference */
    public $app;
}