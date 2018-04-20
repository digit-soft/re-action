<?php
//TODO: Come back later after AssetBundle development

namespace Reaction\Validators;

use yii\web\AssetBundle;

/**
 * This asset bundle provides the javascript files needed for the [[EmailValidator]]s client validation.
 */
class PunycodeAsset extends AssetBundle
{
    public $sourcePath = '@bower/punycode';
    public $js = [
        'punycode.js',
    ];
}
