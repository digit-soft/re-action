<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * Class JqueryAsset
 * @package Reaction\Assets
 */
class JqueryAsset extends AssetBundle
{
    public $sourcePath = '@bower/jquery/dist';

    //public $basePath = '@bower/jquery/dist';

    public $js = [
        'jquery.min.js',
    ];
}