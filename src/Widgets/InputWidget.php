<?php

namespace Reaction\Widgets;

use Reaction;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Base\Model;
use Reaction\Base\Widget;
use Reaction\Helpers\Html;

/**
 * InputWidget is the base class for widgets that collect user inputs.
 *
 * An input widget can be associated with a data [[model]] and an [[attribute]],
 * or a [[name]] and a [[value]]. If the former, the name and the value will
 * be generated automatically (subclasses may call [[renderInputHtml()]] to follow this behavior).
 *
 * Classes extending from this widget can be used in an [[\Reaction\Widgets\ActiveForm|ActiveForm]]
 * using the [[\Reaction\Widgets\ActiveField::widget()|widget()]] method, for example like this:
 *
 * ```php
 * <?= $form->field($model, 'from_date')->widget('WidgetClassName', [
 *     // configure additional widget properties here
 * ]) ?>
 * ```
 *
 * For more details and usage information on InputWidget, see the [guide article on forms](guide:input-forms).
 */
class InputWidget extends Widget
{
    /**
     * @var \Reaction\Widgets\ActiveField active input field, which triggers this widget rendering.
     * This field will be automatically filled up in case widget instance is created via [[\yii\widgets\ActiveField::widget()]].
     */
    public $field;
    /**
     * @var Model the data model that this widget is associated with.
     */
    public $model;
    /**
     * @var string the model attribute that this widget is associated with.
     */
    public $attribute;
    /**
     * @var string the input name. This must be set if [[model]] and [[attribute]] are not set.
     */
    public $name;
    /**
     * @var string the input value.
     */
    public $value;
    /**
     * @var array the HTML attributes for the input tag.
     * @see \Reaction\Helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];


    /**
     * Initializes the widget.
     * If you override this method, make sure you call the parent implementation first.
     */
    public function init()
    {
        if ($this->name === null && !$this->hasModel()) {
            throw new InvalidConfigException("Either 'name', or 'model' and 'attribute' properties must be specified.");
        }
        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->hasModel() ? $this->htmlHlp->getInputId($this->model, $this->attribute) : $this->getId();
        }
        parent::init();
    }

    /**
     * @return bool whether this widget is associated with a data model.
     */
    protected function hasModel()
    {
        return $this->model instanceof Model && $this->attribute !== null;
    }

    /**
     * Render a HTML input tag.
     *
     * This will call [[Html::activeInput()]] if the input widget is [[hasModel()|tied to a model]],
     * or [[Html::input()]] if not.
     *
     * @param string $type the type of the input to create.
     * @return string the HTML of the input field.
     * @see Html::activeInput()
     * @see Html::input()
     */
    protected function renderInputHtml($type)
    {
        if ($this->hasModel()) {
            return $this->htmlHlp->activeInput($type, $this->model, $this->attribute, $this->options);
        }
        return $this->htmlHlp->input($type, $this->name, $this->value, $this->options);
    }
}
