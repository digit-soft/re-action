<?php

namespace Reaction\Base;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Reaction\Exceptions\InvalidCallException;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\UnknownPropertyException;
use Reaction\Helpers\ArrayHelper;

/**
 * Class Component
 * @package Reaction\Base
 */
class Component extends BaseObject implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var string Base path of component */
    protected $_basePath;


    /**
     * Returns the root directory of the component.
     * It defaults to the directory containing the component class file.
     * @return string the root directory of the component.
     */
    public function getBasePath()
    {
        if ($this->_basePath === null) {
            $class = new \ReflectionClass($this);
            $this->_basePath = dirname($class->getFileName());
        }

        return $this->_basePath;
    }

    /**
     * Check attached event listeners for particular event
     * @param string $event
     * @return bool
     */
    public function hasEventListeners($event) {
        return !empty($this->listeners[$event]) || !empty($this->onceListeners[$event]);
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