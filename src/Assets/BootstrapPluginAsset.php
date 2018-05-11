<?php
/**
 * Created by PhpStorm.
 * User: digit
 * Date: 11.05.18
 * Time: 16:55
 */

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
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $isProd = \Reaction::isProd();
        $jsFile = $isProd ? 'bootstrap.min.js' : 'bootstrap.js';
        $this->js[] = 'js/' . $jsFile;
        parent::init();
    }
}