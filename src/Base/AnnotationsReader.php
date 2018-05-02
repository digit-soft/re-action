<?php

namespace Reaction\Base;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use Reaction\Helpers\ClassFinderHelper;

/**
 * Class AnnotationsReader
 * @package Reaction\Base
 * @property SimpleAnnotationReader $reader
 */
class AnnotationsReader extends BaseObject
{
    public $annotationNamespaces = [
        'Reaction\Annotations',
    ];
    /** @var string Annotation reader class name */
    public $readerClass = 'Doctrine\Common\Annotations\SimpleAnnotationReader';
    /** @var SimpleAnnotationReader */
    protected $_reader;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * @param $class
     * @return array
     */
    public function getClass($class)
    {
        $reflector = $this->getClassReflection($class);
        return $this->reader->getClassAnnotations($reflector);
    }

    /**
     * @param $class
     * @param $property
     * @return array
     */
    public function getProperty($class, $property)
    {
        $reflector = $this->getPropertyReflection($class, $property);
        return $this->reader->getPropertyAnnotations($reflector);
    }

    /**
     * @param string|\object $class
     * @param $method
     * @return array
     */
    public function getMethod($class, $method)
    {
        $reflector = $this->getMethodReflection($class, $method);
        $test = $this->reader->getMethodAnnotations($reflector);
        return $this->reader->getMethodAnnotations($reflector);
    }

    /**
     * Get doctrine annotation reader
     * @return mixed|\object
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    protected function getReader() {
        if(!isset($this->_reader)) {
            $annotationClassNames = ClassFinderHelper::findClassesPsr4($this->annotationNamespaces);
            $this->_reader = new CachedReader(
                new IndexedReader(new AnnotationReader()),
                new ArrayCache()
            );
        }
        return $this->_reader;
    }

    /**
     * Get class reflection
     * @param string|\object $class
     * @return \ReflectionClass
     */
    protected function getClassReflection($class) {
        if (is_object($class) && $class instanceof \ReflectionClass) {
            return $class;
        }
        return new \ReflectionClass($class);
    }

    /**
     * Get property reflection
     * @param string|\object $class
     * @param string $property
     */
    protected function getPropertyReflection($class, $property) {
        if (is_object($class) && $class instanceof \ReflectionProperty) {
            return $class;
        }
        return new \ReflectionProperty($class, $property);
    }

    /**
     * Get method reflection
     * @param string|\object $class
     * @param string $method
     */
    protected function getMethodReflection($class, $method) {
        if (is_object($class) && $class instanceof \ReflectionMethod) {
            return $class;
        }
        return new \ReflectionMethod($class, $method);
    }
}