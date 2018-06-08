<?php

namespace Reaction\Widgets\Bootstrap;

/**
 * Class Widget
 * @package Reaction\Widgets\Bootstrap
 */
class Widget extends \Reaction\Base\Widget
{
    use BootstrapWidgetTrait;

    /**
     * @var array the HTML attributes for the widget container tag.
     * @see \Reaction\Helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];

    /**
     * @inheritdoc
     */
    public static function widget($config = [])
    {
        return parent::widget($config);
    }
}