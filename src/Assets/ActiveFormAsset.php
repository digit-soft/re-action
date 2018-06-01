<?php

namespace Reaction\Assets;

use Reaction\Web\AssetBundle;

/**
 * The asset bundle for the [[ActiveForm]] widget.
 */
class ActiveFormAsset extends AssetBundle
{
    public $sourcePath = '@reaction/Static/base';
    public $js = [
        'js/reaction.activeForm.js',
    ];
    public $depends = [
        'Reaction\Assets\ReactionAsset',
    ];
}
