<?php

namespace Reaction\Base;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Reaction\Annotations\CtrlAction;

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
        \Reaction::$app->logger->info($test);
        return $this->reader->getMethodAnnotations($reflector);
    }

    /**
     * Get doctrine annotation reader
     * @return mixed|\object
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    protected function getReader() {
        if(!isset($this->_reader)) {
            //$this->_reader = \Reaction::create($this->readerClass);
            $this->_reader = new AnnotationReader();
            AnnotationRegistry::registerLoader('class_exists');
            //AnnotationRegistry::registerAutoloadNamespaces($this->annotationNamespaces);
            //$test = new CtrlAction();
            foreach ($this->annotationNamespaces as $annotationNamespace) {
                //$this->_reader->addNamespace($annotationNamespace);
            }
            //Reaction::$app->logger->error($this->_reader);
        }
        return $this->_reader;
    }

    /**
     * Get class reflection
     * @param string|\object $class
     * @return \ReflectionClass
     */
    protected function getClassReflection($class) {
        if(is_object($class) && $class instanceof \ReflectionClass) return $class;
        return new \ReflectionClass($class);
    }

    /**
     * Get property reflection
     * @param string|\object $class
     * @param string $property
     */
    protected function getPropertyReflection($class, $property) {
        if(is_object($class) && $class instanceof \ReflectionProperty) return $class;
        return new \ReflectionProperty($class, $property);
    }

    /**
     * Get method reflection
     * @param string|\object $class
     * @param string $method
     */
    protected function getMethodReflection($class, $method) {
        if(is_object($class) && $class instanceof \ReflectionMethod) return $class;
        return new \ReflectionMethod($class, $method);
    }
}