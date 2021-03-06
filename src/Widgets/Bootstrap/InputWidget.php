<?php

namespace Reaction\Widgets\Bootstrap;

/**
 * InputWidget is an adjusted for bootstrap needs version of [[\Reaction\Widgets\InputWidget]].
 */
class InputWidget extends \Reaction\Widgets\InputWidget
{
    use BootstrapWidgetTrait;

    /**
     * @inheritdoc
     */
    public static function widget($config = [])
    {
        return parent::widget($config);
    }
}
