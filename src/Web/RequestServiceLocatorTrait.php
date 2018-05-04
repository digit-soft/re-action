<?php

namespace Reaction\Web;

use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ArrayHelper;

/**
 * Trait RequestServiceLocatorTrait
 * @package Reaction\Web
 */
trait RequestServiceLocatorTrait
{
    /**
     * @var array shared component instances indexed by their IDs
     */
    protected $_components = [];
    /**
     * @var array component definitions indexed by their IDs
     */
    protected $_definitions = [];

    /**
     * Getter magic method.
     * This method is overridden to support accessing components like reading properties.
     * @param string $name component or property name
     * @return mixed the named property value
     * @throws InvalidConfigException
     * @throws \Reaction\Exceptions\UnknownPropertyException
     * @throws \ReflectionException
     */
    public function __get($name)
    {
        $getter = $this->getCustomGetter($name);
        if ($this->hasComponent($name)) {
            return $this->getComponent($name);
        } elseif (method_exists($this, $getter)) {
            return $this->$getter();
        }

        return parent::__get($name);
    }

    /**
     * Sets value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$object->property = $value;`.
     * @param string $name the property name or the event name
     * @param mixed $value the property value
     * @see __get()
     */
    public function __set($name, $value)
    {
        $setter = $this->getCustomSetter($name);
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the named component is loaded.
     * @param string $name the property name or the event name
     * @return bool whether the property value is null
     */
    public function __isset($name)
    {
        $getter = $this->getCustomGetter($name);
        if ($this->hasComponent($name)) {
            return true;
        } elseif (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        }

        return parent::__isset($name);
    }

    /**
     * Sets an object property to null.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `unset($object->property)`.
     *
     * Note that if the property is not defined, this method will do nothing.
     * If the property is read-only, it will throw an exception.
     * @param string $name the property name
     * @see http://php.net/manual/en/function.unset.php
     */
    public function __unset($name)
    {
        $setter = $this->getCustomSetter($name);
        if (method_exists($this, $setter)) {
            $this->$setter(null);
        } else {
            parent::__unset($name);
        }
    }

    /**
     * Returns a value indicating whether the locator has the specified component definition or has instantiated the component.
     * This method may return different results depending on the value of `$checkInstance`.
     *
     * - If `$checkInstance` is false (default), the method will return a value indicating whether the locator has the specified
     *   component definition.
     * - If `$checkInstance` is true, the method will return a value indicating whether the locator has
     *   instantiated the specified component.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool $checkInstance whether the method should check if the component is shared and instantiated.
     * @return bool whether the locator has the specified component definition or has instantiated the component.
     * @see set()
     */
    public function hasComponent($id, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_components[$id]) : isset($this->_definitions[$id]);
    }

    /**
     * Returns the component instance with the specified ID.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool   $throwException whether to throw an exception if `$id` is not registered with the locator before.
     * @return \object|null the component of the specified ID. If `$throwException` is false and `$id`
     * is not registered before, null will be returned.
     * @throws InvalidConfigException if `$id` refers to a nonexistent component ID
     * @see has()
     * @see set()
     */
    public function getComponent($id, $throwException = true)
    {
        if (isset($this->_components[$id])) {
            return $this->_components[$id];
        }

        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];
            if (is_object($definition) && !$definition instanceof \Closure) {
                return $this->_components[$id] = $definition;
            }
            $params = [];
            if(is_array($definition) && isset($definition[0])) {
                $config = is_array($definition[0]) ? $definition[0] : ['class' => $definition[0]];
                unset($definition[0]);
                if(isset($definition[1])) {
                    $params = (array)$definition[1];
                    unset($definition[1]);
                }
                $definition = \Reaction\Helpers\ArrayHelper::merge($definition, $config);
            }
            return $this->_components[$id] = \Reaction::create($definition, $params);
        } elseif ($throwException) {
            throw new InvalidConfigException("Unknown component ID: $id");
        }

        return null;
    }

    /**
     * Registers a component definition with this locator.
     *
     * For example,
     *
     * ```php
     * // a class name
     * $locator->set('cache', 'yii\caching\FileCache');
     *
     * // a configuration array
     * $locator->set('db', [
     *     'class' => 'yii\db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // an anonymous function
     * $locator->set('cache', function ($params) {
     *     return new \yii\caching\FileCache;
     * });
     *
     * // an instance
     * $locator->set('cache', new \yii\caching\FileCache);
     * ```
     *
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * @param string $id component ID (e.g. `db`).
     * @param mixed $definition the component definition to be registered with this locator.
     * It can be one of the following:
     *
     * - a class name
     * - a configuration array: the array contains name-value pairs that will be used to
     *   initialize the property values of the newly created object when [[get()]] is called.
     *   The `class` element is required and stands for the the class of the object to be created.
     * - a PHP callable: either an anonymous function or an array representing a class method (e.g. `['Foo', 'bar']`).
     *   The callable will be called by [[get()]] to return an object associated with the specified component ID.
     * - an object: When [[get()]] is called, this object will be returned.
     *
     * @throws InvalidConfigException if the definition is an invalid configuration array
     */
    public function setComponent($id, $definition)
    {
        unset($this->_components[$id]);

        if ($definition === null) {
            unset($this->_definitions[$id]);
            return;
        }

        //Extract definition from DI Definition
        if($definition instanceof \Reaction\DI\Definition) {
            $definition = $definition->dumpArrayDefinition();
        }

        if (is_string($definition)) {
            $config = ['class' => $definition];
        } elseif (\Reaction\Helpers\ArrayHelper::isIndexed($definition) && count($definition) === 2) {
            $config = $definition[0];
            $params = $definition[1];
        } else {
            $config = $definition;
        }

        if (is_array($config)) {
            $config = ArrayHelper::merge(['request' => $this], $config);
            $definition = isset($params) ? [$config, $params] : $config;
        }

        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;
            } else {
                throw new InvalidConfigException("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    /**
     * Removes the component from the locator.
     * @param string $id the component ID
     */
    public function clearComponent($id)
    {
        unset($this->_definitions[$id], $this->_components[$id]);
    }

    /**
     * Returns the list of the component definitions or the loaded component instances.
     * @param bool $returnDefinitions whether to return component definitions instead of the loaded component instances.
     * @return array the list of the component definitions or the loaded component instances (ID => definition or instance).
     */
    public function getComponents($returnDefinitions = true)
    {
        return $returnDefinitions ? $this->_definitions : $this->_components;
    }

    /**
     * Registers a set of component definitions in this locator.
     *
     * This is the bulk version of [[set()]]. The parameter should be an array
     * whose keys are component IDs and values the corresponding component definitions.
     *
     * For more details on how to specify component IDs and definitions, please refer to [[set()]].
     *
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * The following is an example for registering two component definitions:
     *
     * ```php
     * [
     *     'db' => [
     *         'class' => 'Reaction\Db\Connection',
     *         'dsn' => 'sqlite:path/to/file.db',
     *     ],
     *     'cache' => [
     *         'class' => 'Reaction\Caching\DbCache',
     *         'db' => 'db',
     *     ],
     * ]
     * ```
     *
     * @param array $components component definitions or instances
     * @throws InvalidConfigException
     */
    public function setComponents(array $components = [])
    {
        foreach ($components as $id => $component) {
            $this->setComponent($id, $component);
        }
    }

    /**
     * Get possible getter for Request
     * @param string $name
     * @return string
     */
    private function getCustomGetter($name) {
        return '_get' . $name;
    }

    /**
     * Get possible setter for Request
     * @param string $name
     * @return string
     */
    private function getCustomSetter($name) {
        return '_set' . $name;
    }
}