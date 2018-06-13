<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * Class BootstrapAsset
 * @package Reaction\Assets
 */
class BootstrapAsset extends AssetBundle
{
    public $sourcePath = '@bower/bootstrap/dist';

    public $css = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $isProd = \Reaction::isProd();
        $cssFile = $isProd ? 'bootstrap.min.css' : 'bootstrap.css';
        $this->css[] = 'css/' . $cssFile;
        parent::init();
    }
}