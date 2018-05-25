<?php

namespace Reaction\Helpers;

/**
 * Class ReflectionHelper.
 * Helper static for some operations where Reflection is needed
 * @package Reaction\Helpers
 */
class ReflectionHelper
{
    const REFLECTION_CLASS = 'class';
    const REFLECTION_METHOD = 'method';

    /**
     * @var array Reflections cache
     */
    protected static $_reflections = [
        self::REFLECTION_CLASS => [],
        self::REFLECTION_METHOD => [],
    ];

    /**
     * Check that class (by name) implements some interface
     * @param string $className
     * @param string $interfaceName
     * @return bool
     */
    public static function isImplements($className, $interfaceName) {
        $implementations = class_implements($className, true);
        return isset($implementations[$interfaceName]);
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
    public static function getMethodReflection($objectOrName, $methodName) {
        $cached = static::getReflectionFromCache(static::REFLECTION_METHOD, $objectOrName, $methodName);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $reflection = new \ReflectionMethod($objectOrName, $methodName);
            return static::setReflectionToCache($reflection, static::REFLECTION_METHOD, $objectOrName, $methodName);
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
    public static function getClassReflection($objectOrName) {
        $cached = static::getReflectionFromCache(static::REFLECTION_CLASS, $objectOrName);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $reflection = new \ReflectionClass($objectOrName);
            return static::setReflectionToCache($reflection, static::REFLECTION_CLASS, $objectOrName);
        } catch (\ReflectionException $exception) {
            $reflection = null;
        }
        return $reflection;
    }

    /**
     * Get reflection from cache
     * @param string $type
     * @param string|object $objectOrName
     * @param mixed  ...$params
     * @return mixed
     */
    protected static function getReflectionFromCache($type = self::REFLECTION_CLASS, $objectOrName, ...$params) {
        $cache = &static::$_reflections[$type];
        $key = static::getCacheKey($type, $objectOrName, ...$params);
        return isset($cache[$key]) ? $cache[$key] : null;
    }

    /**
     * Save reflection to cache
     * @param \Reflector $reflection
     * @param string     $type
     * @param null       $objectOrName
     * @param mixed      ...$params
     * @return mixed
     */
    protected static function setReflectionToCache($reflection, $type = self::REFLECTION_CLASS, $objectOrName = null, ...$params) {
        if (null === $reflection || null === $objectOrName) {
            return null;
        }
        $key = static::getCacheKey($type, $objectOrName, $params);
        static::$_reflections[$type][$key] = $reflection;
        return $reflection;
    }

    /**
     * Get object cache key
     * @param string $type
     * @param string|object $objectOrName
     * @param mixed[]  ...$params
     * @return mixed|string
     */
    protected static function getCacheKey($type = self::REFLECTION_CLASS, $objectOrName, ...$params) {
        if (is_object($objectOrName)) {
            $className = static::getObjectClassName($objectOrName);
        } else {
            $className = $objectOrName;
        }
        $key = $className;
        switch ($type) {
            case static::REFLECTION_METHOD:
                $method = $params[0];
                $key .= '::' . $method;
                break;
        }
        return $key;
    }

    /**
     * Get class name of object or its reflection
     * @param object $object
     * @return mixed|string
     */
    protected static function getObjectClassName($object)
    {
        $nameGetters = [
            \ReflectionClass::class => 'getName()',
            \ReflectionMethod::class => 'class',
        ];
        foreach ($nameGetters as $class => $getter) {
            if ($object instanceof $class) {
                $isMethod = substr($getter, -2) === '()';
                if ($isMethod) {
                    $getter = substr($getter, 0, -2);
                    return call_user_func([$object, $getter]);
                }
                return $object->$getter;
            }
        }
        return get_class($object);
    }
}