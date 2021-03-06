<?php

namespace Reaction\Base;

use Reaction\Helpers\Request\HelpersGroup;
use Reaction\I18n\RequestLanguageGetterInterface;
use Reaction\RequestApplicationInterface;

/**
 * Class RequestAppComponent
 * @package Reaction\Base
 * @property HelpersGroup $hlp
 */
class RequestAppComponent extends Component implements RequestAppComponentInterface, RequestLanguageGetterInterface
{
    /** @var RequestApplicationInterface An request application instance reference */
    public $app;

    /**
     * Get language for current request
     * @return string
     */
    public function getRequestLanguage()
    {
        return $this->app->language;
    }
}