<?php

namespace Reaction\Widgets\Bootstrap;

use Reaction\Helpers\Html;
use Reaction\Helpers\ArrayHelper;

/**
 * ButtonDropdown renders a group or split button dropdown bootstrap component.
 *
 * For example,
 *
 * ```php
 * // a button group using Dropdown widget
 * echo ButtonDropdown::widget([
 *     'label' => 'Action',
 *     'dropdown' => [
 *         'items' => [
 *             ['label' => 'DropdownA', 'url' => '/'],
 *             ['label' => 'DropdownB', 'url' => '#'],
 *         ],
 *     ],
 * ]);
 * ```
 * @see http://getbootstrap.com/javascript/#buttons
 * @see http://getbootstrap.com/components/#btn-dropdowns
 */
class ButtonDropdown extends Widget
{
    /**
     * @var string the button label
     */
    public $label = 'Button';
    /**
     * @var array the HTML attributes of the button group container.
     * @see \Reaction\Helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];
    /**
     * @var array the HTML attributes of the button.
     * @see \Reaction\Helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $buttonOptions = [];
    /**
     * @var array the configuration array for [[Dropdown]].
     */
    public $dropdown = [];
    /**
     * @var boolean whether to display a group of split-styled button group.
     */
    public $split = false;
    /**
     * @var boolean whether to render dropup.
     */
    public $dropUp = false;
    /**
     * @var string the tag to use to render the button
     */
    public $tagName = 'button';
    /**
     * @var boolean whether the label should be HTML-encoded.
     */
    public $encodeLabel = true;
    /**
     * @var string name of a class to use for rendering dropdowns withing this widget. Defaults to [[Dropdown]].
     */
    public $dropdownClass = 'Reaction\Widgets\Bootstrap\Dropdown';


    /**
     * Renders the widget.
     */
    public function run()
    {
        unset($this->options['id']);
        Html::addCssClass($this->options, ['widget' => 'btn-group']);
        if($this->dropUp) {
            Html::addCssClass($this->options, 'dropup');
        }
        $options = $this->options;
        $tag = ArrayHelper::remove($options, 'tag', 'div');

        $this->registerPlugin('dropdown');
        return implode("\n", [
            $this->htmlHlp->beginTag($tag, $options),
            $this->renderButton(),
            $this->renderDropdown(),
            $this->htmlHlp->endTag($tag)
        ]);
    }

    /**
     * Generates the button dropdown.
     * @return string the rendering result.
     */
    protected function renderButton()
    {
        Html::addCssClass($this->buttonOptions, ['widget' => 'btn']);
        $label = $this->label;
        if ($this->encodeLabel) {
            $label = Html::encode($label);
        }
        if ($this->split) {
            $options = $this->buttonOptions;
            $this->buttonOptions['data-toggle'] = 'dropdown';
            Html::addCssClass($this->buttonOptions, ['toggle' => 'dropdown-toggle']);
            $splitButton = Button::widget([
                'label' => '',
                'encodeLabel' => false,
                'options' => $this->buttonOptions,
                'view' => $this->getView(),
                'app' => $this->app,
            ]);
        } else {
            $options = $this->buttonOptions;
            if (!isset($options['href']) && $this->tagName === 'a') {
                $options['href'] = '#';
            }
            Html::addCssClass($options, ['toggle' => 'dropdown-toggle']);
            $options['data-toggle'] = 'dropdown';
            $splitButton = '';
        }

        return Button::widget([
            'app' => $this->app,
            'tagName' => $this->tagName,
            'label' => $label,
            'options' => $options,
            'encodeLabel' => false,
            'view' => $this->getView(),
        ]) . "\n" . $splitButton;
    }

    /**
     * Generates the dropdown menu.
     * @return string the rendering result.
     */
    protected function renderDropdown()
    {
        $config = $this->dropdown;
        $config['app'] = $this->app;
        $config['clientOptions'] = false;
        $config['view'] = $this->getView();
        /** @var Widget $dropdownClass */
        $dropdownClass = $this->dropdownClass;
        return $dropdownClass::widget($config);
    }
}
