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

    /** @var array Components of Component ^_^ */
    protected $_components = [];

    /**
     * Check attached event listeners for particular event
     * @param string $event
     * @return bool
     */
    public function hasEventListeners($event) {
        return !empty($this->listeners[$event]) || !empty($this->onceListeners[$event]);
    }

    /**
     * Set component
     * @param string       $name Component name
     * @param array|string $config Params for Reaction::configure() if array
     * or DI container entry name if string
     * @param array        $params Constructor params for Reaction::create()
     * @throws InvalidConfigException
     */
    public function setComponent($name, $config = [], $params = []) {
        $nameInDi = get_class($this) . '.' . $name;
        if(empty($config)) {
            $nameInDi = $name;
        } if(is_string($config)) {
            $nameInDi = $config;
        //Write new definition to DI
        } elseif(is_callable($config) || is_object($config)) {
            \Reaction::$di->set($nameInDi, $config);
        //Array as DI definition name
        } elseif (is_array($config) && isset($config['class'])) {
            $nameInDi = $config;
        }

        $this->_components[$name] = [
            'di' => $nameInDi,
            'params' => $params,
        ];
    }

    /**
     * Set components bulk
     * @param array $components
     */
    public function setComponents(array $components = []) {
        foreach ($components as $name => $definition) {
            if(!is_string($name)) continue;
            $config = $definition;
            $params = [];
            if(is_array($definition) && isset($definition['config']) && isset($definition['params'])) {
                $config = $definition['config'];
                $params = $definition['params'];
            }
            $this->setComponent($name, $config, $params);
        }
    }

    /**
     * Get component by name
     * @param $name
     * @return mixed|\object
     * @throws InvalidConfigException
     * @throws UnknownPropertyException
     * @throws \ReflectionException
     */
    public function getComponent($name) {
        if(isset($this->_components[$name])) {
            $definition = $this->_components[$name];
            return \Reaction::create($definition['di'], $definition['params']);
        }
        throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Returns the value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $object->property;`.
     * @param string $name the property name
     * @return mixed the property value
     * @throws InvalidConfigException
     * @throws UnknownPropertyException if the property is not defined
     * @throws \ReflectionException
     * @see __set()
     */
    public function __get($name)
    {
        $getter = 'get' . $name;
        if(isset($this->_components[$name])) {
            return $this->getComponent($name);
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