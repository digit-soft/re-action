<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * Class ReactionAsset
 * @package Reaction\Assets
 */
class ReactionAsset extends AssetBundle
{
    public $sourcePath = '@reaction/Static/base';

    public $js = [
        'js/reaction.js',
    ];

    public $css = [
        'css/reaction.css',
    ];

    public $depends = [
        BootstrapAsset::class,
        JqueryAsset::class,
    ];
}