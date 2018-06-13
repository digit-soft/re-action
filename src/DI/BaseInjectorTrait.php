<?php

namespace Reaction\DI;

/**
 * Trait BaseInjectorTrait
 * @package Reaction\DI
 */
trait BaseInjectorTrait
{
    /**
     * @var string Event name that triggers when there is need to inject data to objects
     */
    public $eventChildrenInject = 'childrenInject';
    /**
     * @var array Array of injectable objects
     */
    protected $_injectableObjects = [];

    /**
     * Inject data to children objects.
     * Children means those objects that will be received from further operations like files find, Db models populate
     * @param array|object $injectObjects
     * @return self
     */
    public function injectToChildren($injectObjects = [])
    {
        $this->_injectableObjects = is_array($injectObjects) ? $injectObjects : [$injectObjects];
        $this->attachInjectEventHandler();
        return $this;
    }

    /**
     * Inject data to one object
     * @param object  $object
     * @param mixed[] $injectObjects
     */
    protected function injectTo($object, $injectObjects = null)
    {
        if (!isset($injectObjects)) {
            $injectObjects = $this->getInjectableProperties();
        }
        if (!empty($injectObjects) && $object instanceof InjectionPossibleInterface) {
            $object->inject($injectObjects);
        }
    }

    /**
     * Inject data to multiple objects
     * @param InjectionPossibleInterface[] $objects
     * @param mixed[]                      $injectObjects
     */
    protected function injectToMultiple($objects = [], $injectObjects = null)
    {
        if (!isset($injectObjects)) {
            $injectObjects = $this->getInjectableProperties();
        }
        if (empty($injectObjects)) {
            return;
        }
        foreach ($objects as $object) {
            $this->injectTo($object, $injectObjects);
        }
    }

    /**
     * Get injectable objects
     * @return array
     */
    protected function getInjectableObjects()
    {
        $properties = $this->getInjectableProperties();
        $objects = [];
        foreach ($properties as $property) {
            $object = $this->{$property};
            if ($object !== null) {
                $objects[] = $object;
            }
        }
        return $objects;
    }

    /**
     * Get property names of injectable objects
     * @return array
     */
    protected function getInjectableProperties()
    {
        return [];
    }

    /**
     * Attach event handler to injector
     */
    protected function attachInjectEventHandler()
    {
        $this->removeAllListeners($this->eventChildrenInject);
        $this->on($this->eventChildrenInject, function($objects) {
            if (is_array($objects)) {
                $this->injectToMultiple($objects, $this->_injectableObjects);
            } elseif(is_object($objects)) {
                $this->injectTo($objects, $this->_injectableObjects);
            }
        });
    }
}