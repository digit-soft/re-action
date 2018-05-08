<?php

namespace Reaction\Helpers;

/**
 * Class ReflectionHelper.
 * Helper static for some operations where Reflection is needed
 * @package Reaction\Helpers
 */
class ReflectionHelper
{
    /** @var \ReflectionClass[] Reflection classes cached */
    protected static $_reflectionClasses = [];
    /** @var \ReflectionMethod[] Reflection methods cached */
    protected static $_reflectionMethods = [];

    /**
     * Check that class (by name) implements some interface
     * @param string $className
     * @param string $interfaceName
     * @return bool
     */
    public static function isImplements($className, $interfaceName) {
        $reflection = static::getClassReflection($className);
        if ($reflection === null) {
            return false;
        }
        return $reflection->implementsInterface($interfaceName);
    }

    /**
     * Check that class is instantiable
     * @param string $className
     * @return bool
     */
    public static function isInstantiable($className) {
        $reflection = static::getClassReflection($className);
        if ($reflection === null) {
            return false;
        }
        return $reflection->isInstantiable();
    }

    /**
     * Get class method reflection
     * @param string|object $objectOrName
     * @param string $methodName
     * @return null|\ReflectionMethod
     */
    protected static function getMethodReflection($objectOrName, $methodName) {
        try {
            $reflection = new \ReflectionMethod($objectOrName, $methodName);
            $className = is_object($objectOrName) ? get_class($objectOrName) : $objectOrName;
            $key = $className . '::' . $methodName;
            static::$_reflectionMethods[$key] = $reflection;
        } catch (\ReflectionException $exception) {
            $reflection = null;
        }
        return $reflection;
    }

    /**
     * Get class reflection
     * @param string|object $objectOrName
     * @return null|\ReflectionClass
     */
    protected static function getClassReflection($objectOrName) {
        $cached = static::getClassReflectionFromCache($objectOrName);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $reflection = new \ReflectionClass($objectOrName);
            $className = is_object($objectOrName) ? get_class($objectOrName) : $objectOrName;
            static::$_reflectionClasses[$className] = $reflection;
        } catch (\ReflectionException $exception) {
            $reflection = null;
        }
        return $reflection;
    }

    /**
     * Get class reflection from static cache
     * @param string|object $objectOrName
     * @return \ReflectionClass|null
     */
    protected static function getClassReflectionFromCache($objectOrName) {
        $className = is_object($objectOrName) ? get_class($objectOrName) : $objectOrName;
        return isset(static::$_reflectionClasses[$className]) ? static::$_reflectionClasses[$className] : null;
    }

    /**
     * Get class method reflection from static cache
     * @param string|object $objectOrName
     * @param string        $methodName
     * @return \ReflectionMethod|null
     */
    protected static function getMethodReflectionFromCache($objectOrName, $methodName) {
        $className = is_object($objectOrName) ? get_class($objectOrName) : $objectOrName;
        $key = $className . '::' . $methodName;
        return isset(static::$_reflectionMethods[$key]) ? static::$_reflectionMethods[$key] : null;
    }
}