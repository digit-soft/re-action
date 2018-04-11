<?php

namespace Reaction\Base;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Reaction\Exceptions\InvalidCallException;
use Reaction\Exceptions\UnknownPropertyException;

/**
 * Class Component
 * @package Reaction\Base
 */
class Component extends BaseObject implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var array Components of Component ^_^ */
    protected $_components = [];

    /**
     * Returns the value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $object->property;`.
     * @param string $name the property name
     * @return mixed the property value
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is write-only
     * @see __set()
     */
    public function __get($name)
    {
        $getter = 'get' . $name;
        if(isset($this->_components[$name])) {
            return $this->_components[$name];
        } elseif (method_exists($this, $getter)) {
            return $this->$getter();
        } elseif (method_exists($this, 'set' . $name)) {
            throw new InvalidCallException('Getting write-only property: ' . get_class($this) . '::' . $name);
        }

        throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Sets value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$object->property = $value;`.
     * @param string $name the property name or the event name
     * @param mixed $value the property value
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is read-only
     * @see __get()
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            // set property
            $this->$setter($value);

            return;
        } elseif (strncmp($name, 'on ', 3) === 0) {
            // on event: attach event handler
            $this->on(trim(substr($name, 3)), $value);

            return;
        }

        if (method_exists($this, 'get' . $name)) {
            throw new InvalidCallException('Setting read-only property: ' . get_class($this) . '::' . $name);
        }

        throw new UnknownPropertyException('Setting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * This method is called after the object is created by cloning an existing one.
     * It removes all listeners because they are attached to the old object.
     */
    public function __clone()
    {
        $this->listeners = [];
        $this->onceListeners = [];
    }
}