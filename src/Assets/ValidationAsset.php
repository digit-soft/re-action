<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * This asset bundle provides the javascript files for client validation.
 */
class ValidationAsset extends AssetBundle
{
    public $sourcePath = '@reaction/Static/base';
    public $js = [
        'js/reaction.validation.js',
    ];
    public $depends = [
        'Reaction\Assets\ReactionAsset',
    ];
}
