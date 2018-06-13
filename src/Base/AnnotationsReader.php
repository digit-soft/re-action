<?php

namespace Reaction\Base;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\IndexedReader;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Helpers\ClassFinderHelper;
use Reaction\Helpers\ReflectionHelper;

/**
 * Class AnnotationsReader
 * @package Reaction\Base
 * @property AnnotationReader|IndexedReader $reader
 */
class AnnotationsReader extends BaseObject
{
    public $annotationNamespaces = [
        'Reaction\Annotations',
    ];
    /** @var string Annotation reader class name */
    public $readerClass = 'Doctrine\Common\Annotations\AnnotationReader';
    /**
     * @var AnnotationReader|IndexedReader Annotation reader
     */
    protected $_reader;
    /** @var array Cached annotations */
    protected $_cache = [];

    /**
     * Get all annotations for class
     * @param string|object $class
     * @param bool          $refresh
     * @return array
     */
    public function getClass($class, $refresh = false)
    {
        $reflector = $this->getClassReflection($class);
        $cacheKey = $reflector->getName();
        if (!$refresh && $this->cacheExists($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }
        /** @var array|null $annotations */
        $annotations = $this->reader->getClassAnnotations($reflector);
        $annotations = is_array($annotations) ? $annotations : [];
        $this->setToCache($cacheKey, $annotations);
        return $annotations;
    }

    /**
     * Get class annotation by its class|interface name
     * @param string|object $class
     * @param string|object $annotationClass
     * @param bool          $refresh
     * @return object|null
     */
    public function getClassExact($class, $annotationClass, $refresh = false)
    {
        $annotations = $this->getClass($class, $refresh);
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationClass) {
                return $annotation;
            }
        }
        return null;
    }

    /**
     * Get all annotations for property
     * @param string|object $class
     * @param string        $property
     * @param bool          $refresh
     * @return array
     */
    public function getProperty($property, $class = null, $refresh = false)
    {
        $propertyReflection = $this->getPropertyReflection($property, $class);
        $classReflection = $propertyReflection->getDeclaringClass();
        $cacheKey = $classReflection->getName() . '$' . $propertyReflection->getName();
        if (!$refresh && $this->cacheExists($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }
        /** @var array|null $annotations */
        $annotations = $this->reader->getPropertyAnnotations($propertyReflection);
        $annotations = is_array($annotations) ? $annotations : [];
        $this->setToCache($cacheKey, $annotations);
        return $annotations;
    }

    /**
     * Get property annotation by its class|interface name
     * @param string|\ReflectionProperty        $property
     * @param string|object $annotationClass
     * @param string|object $class
     * @param bool          $refresh
     * @return object|null
     */
    public function getPropertyExact($property, $annotationClass, $class = null, $refresh = false)
    {
        $annotations = $this->getProperty($property, $class, $refresh);
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationClass) {
                return $annotation;
            }
        }
        return null;
    }

    /**
     * Get all annotations for method
     * @param string|object|null       $class
     * @param string|\ReflectionMethod $method
     * @param bool                     $refresh
     * @return array
     */
    public function getMethod($method, $class = null, $refresh = false)
    {
        $methodReflection = $this->getMethodReflection($method, $class);
        $classReflection = $methodReflection->getDeclaringClass();
        $cacheKey = $classReflection->getName() . '#' . $methodReflection->getName();
        if (!$refresh && $this->cacheExists($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }
        /** @var array|null $annotations */
        $annotations = $this->reader->getMethodAnnotations($methodReflection);
        $annotations = is_array($annotations) ? $annotations : [];
        $this->setToCache($cacheKey, $annotations);
        return $annotations;
    }

    /**
     * Get method annotation by its class|interface name
     * @param string|\ReflectionMethod $method
     * @param string|object            $annotationClass
     * @param string|object|null       $class
     * @param bool                     $refresh
     * @return object|null
     */
    public function getMethodExact($method, $annotationClass, $class = null, $refresh = false)
    {
        $annotations = $this->getMethod($method, $class, $refresh);
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationClass) {
                return $annotation;
            }
        }
        return null;
    }

    /**
     * Get doctrine annotation reader
     * @return mixed|object
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    protected function getReader()
    {
        if (!isset($this->_reader)) {
            ClassFinderHelper::findClassesPsr4($this->annotationNamespaces);
            $this->_reader = new IndexedReader(new AnnotationReader());
        }
        return $this->_reader;
    }

    /**
     * Get class reflection
     * @param string|object $class
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    protected function getClassReflection($class)
    {
        if (is_object($class) && $class instanceof \ReflectionClass) {
            return $class;
        }
        return ReflectionHelper::getClassReflection($class);
    }

    /**
     * Get property reflection
     * @param string|\ReflectionProperty $property
     * @param string|object|null         $class
     * @return \ReflectionProperty
     */
    protected function getPropertyReflection($property, $class = null)
    {
        if (is_object($property) && $property instanceof \ReflectionProperty) {
            return $property;
        }
        if ($class === null) {
            throw new InvalidArgumentException("Argument 'class' must be set");
        }
        return ReflectionHelper::getPropertyReflection($class, $property);
    }

    /**
     * Get method reflection
     * @param string|\ReflectionMethod $method
     * @param string|object|null       $class
     * @return \ReflectionMethod
     */
    protected function getMethodReflection($method, $class = null)
    {
        if (is_object($method) && $method instanceof \ReflectionMethod) {
            return $method;
        }
        if ($class === null) {
            throw new InvalidArgumentException("Argument 'class' must be set");
        }
        return ReflectionHelper::getMethodReflection($class, $method);
    }

    /**
     * Get data from cache
     * @param string $key
     * @return mixed
     */
    protected function getFromCache($key)
    {
        return isset($this->_cache[$key]) ? $this->_cache[$key] : null;
    }

    /**
     * Set data to cache
     * @param string $key
     * @param mixed|null $value
     */
    protected function setToCache($key, $value = null)
    {
        if (!isset($value)) {
            unset($this->_cache[$value]);
            return;
        }
        $this->_cache[$key] = $value;
    }

    /**
     * Check that cache by given key exists
     * @param string $key
     * @return bool
     */
    protected function cacheExists($key)
    {
        return isset($this->_cache[$key]);
    }
}