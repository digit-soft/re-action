<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * Class PopperJsAsset.
 * Temporary not used
 * @package Reaction\Assets
 */
class PopperJsAsset extends AssetBundle
{
    public $sourcePath = '@bower/popper.js/dist/esm';

    public $js = [];

    public $depends = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $isProd = \Reaction::isProd();
        $this->js[] = $isProd ? 'popper.min.js' : 'popper.js';
        parent::init();
    }
}