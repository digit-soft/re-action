<?php
//TODO: Come back later after AssetBundle development

namespace Reaction\Validators;

use yii\web\AssetBundle;

/**
 * This asset bundle provides the javascript files for client validation.
 */
class ValidationAsset extends AssetBundle
{
    public $sourcePath = '@reaction/Assets';
    public $js = [
        'yii.validation.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
}
