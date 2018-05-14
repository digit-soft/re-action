<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * Class BootstrapPluginAsset
 * @package Reaction\Assets
 */
class BootstrapPluginAsset extends AssetBundle
{
    public $sourcePath = '@bower/bootstrap/dist';

    public $js = [];

    public $depends = [
        BootstrapAsset::class,
        JqueryAsset::class,
        //PopperJsAsset::class,
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $isProd = \Reaction::isProd();
        $this->js[] = 'js/' . ($isProd ? 'bootstrap.bundle.min.js' : 'bootstrap.bundle.js');
        parent::init();
    }
}