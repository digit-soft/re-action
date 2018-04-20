<?php

namespace Reaction\Validators;

/**
 * BooleanValidator checks if the attribute value is a boolean value.
 *
 * Possible boolean values can be configured via the [[trueValue]] and [[falseValue]] properties.
 * And the comparison can be either [[strict]] or not.
 */
class BooleanValidator extends Validator
{
    /**
     * @var mixed the value representing true status. Defaults to '1'.
     */
    public $trueValue = '1';
    /**
     * @var mixed the value representing false status. Defaults to '0'.
     */
    public $falseValue = '0';
    /**
     * @var bool whether the comparison to [[trueValue]] and [[falseValue]] is strict.
     * When this is true, the attribute value and type must both match those of [[trueValue]] or [[falseValue]].
     * Defaults to false, meaning only the value needs to be matched.
     */
    public $strict = false;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = \Reaction::t('yii', '{attribute} must be either "{true}" or "{false}".');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        if ($this->strict) {
            $valid = $value === $this->trueValue || $value === $this->falseValue;
        } else {
            $valid = $value == $this->trueValue || $value == $this->falseValue;
        }

        if (!$valid) {
            return [$this->message, [
                'true' => $this->trueValue === true ? 'true' : $this->trueValue,
                'false' => $this->falseValue === false ? 'false' : $this->falseValue,
            ]];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        //TODO: Make a Validation Asset
        //ValidationAsset::register($view);
        $options = $this->getClientOptions($model, $attribute);

        return 'yii.validation.boolean(value, messages, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    }

    /**
     * {@inheritdoc}
     */
    public function getClientOptions($model, $attribute)
    {
        $options = [
            'trueValue' => $this->trueValue,
            'falseValue' => $this->falseValue,
            'message' => $this->formatMessage($this->message, [
                'attribute' => $model->getAttributeLabel($attribute),
                'true' => $this->trueValue === true ? 'true' : $this->trueValue,
                'false' => $this->falseValue === false ? 'false' : $this->falseValue,
            ]),
        ];
        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }
        if ($this->strict) {
            $options['strict'] = 1;
        }

        return $options;
    }
}
