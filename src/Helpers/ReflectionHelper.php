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
    const REFLECTION_PROPERTY = 'property';

    /**
     * @var array Reflections cache
     */
    protected static $_reflections = [
        self::REFLECTION_CLASS => [],
        self::REFLECTION_METHOD => [],
        self::REFLECTION_PROPERTY => [],
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
     * Get class PHPDoc full text
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getClassDocFull($objectOrName) {
        return static::getClassDoc($objectOrName, 'parseDocCommentDetail');
    }

    /**
     * Get class PHPDoc short text (first line)
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getClassDocSummary($objectOrName) {
        return static::getClassDoc($objectOrName, 'parseDocCommentSummary');
    }

    /**
     * Get class PHPDoc tags array
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getClassDocTags($objectOrName) {
        return static::getClassDoc($objectOrName, 'parseDocCommentTags');
    }

    /**
     * Get class method PHPDoc full text
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getMethodDocFull($objectOrName, $method) {
        return static::getMethodPropertyDoc($objectOrName, $method, static::REFLECTION_METHOD,'parseDocCommentDetail');
    }

    /**
     * Get class method PHPDoc short text (first line)
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getMethodDocSummary($objectOrName, $method) {
        return static::getMethodPropertyDoc($objectOrName, $method, static::REFLECTION_METHOD, 'parseDocCommentSummary');
    }

    /**
     * Get class method PHPDoc tags array
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getMethodDocTags($objectOrName, $method) {
        return static::getMethodPropertyDoc($objectOrName, $method, static::REFLECTION_METHOD, 'parseDocCommentTags');
    }

    /**
     * Get class property PHPDoc full text
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getPropertyDocFull($objectOrName, $method) {
        return static::getMethodPropertyDoc($objectOrName, $method, static::REFLECTION_PROPERTY,'parseDocCommentDetail');
    }

    /**
     * Get class property PHPDoc short text (first line)
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getPropertyDocSummary($objectOrName, $method) {
        return static::getMethodPropertyDoc($objectOrName, $method, static::REFLECTION_PROPERTY, 'parseDocCommentSummary');
    }

    /**
     * Get class property PHPDoc tags array
     * @param string|object $objectOrName
     * @return array|null|string
     */
    public static function getPropertyDocTags($objectOrName, $method) {
        return static::getMethodPropertyDoc($objectOrName, $method, static::REFLECTION_PROPERTY, 'parseDocCommentTags');
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
     * Get property reflection
     * @param string|object $objectOrName
     * @param string        $propertyName
     * @return mixed|null|\ReflectionProperty
     */
    public static function getPropertyReflection($objectOrName, $propertyName) {
        $cached = static::getReflectionFromCache(static::REFLECTION_PROPERTY, $objectOrName, $propertyName);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $reflection = new \ReflectionProperty($objectOrName, $propertyName);
            return static::setReflectionToCache($reflection, static::REFLECTION_PROPERTY, $objectOrName, $propertyName);
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
            case static::REFLECTION_PROPERTY:
                $methodOrProperty = $params[0];
                $key .= '::' . $methodOrProperty;
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
            \ReflectionProperty::class => 'class',
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

    /**
     * Parses the comment block into tags.
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection the comment block
     * @return array the parsed tags
     */
    protected static function parseDocCommentTags($reflection)
    {
        $comment = $reflection->getDocComment();
        $comment = "@description \n" . strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($comment, '/'))), "\r", '');
        $parts = preg_split('/^\s*@/m', $comment, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($parts as $part) {
            if (preg_match('/^(\w+)(.*)/ms', trim($part), $matches)) {
                $name = $matches[1];
                if (!isset($tags[$name])) {
                    $tags[$name] = trim($matches[2]);
                } elseif (is_array($tags[$name])) {
                    $tags[$name][] = trim($matches[2]);
                } else {
                    $tags[$name] = [$tags[$name], trim($matches[2])];
                }
            }
        }

        return $tags;
    }

    /**
     * Returns the first line of docblock.
     *
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty  $reflection
     * @return string
     */
    protected static function parseDocCommentSummary($reflection)
    {
        $docLines = preg_split('~\R~u', $reflection->getDocComment());
        if (isset($docLines[1])) {
            return trim($docLines[1], "\t *");
        }

        return '';
    }

    /**
     * Returns full description from the docblock.
     *
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection
     * @return string
     */
    protected static function parseDocCommentDetail($reflection)
    {
        $comment = strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($reflection->getDocComment(), '/'))), "\r", '');
        if (preg_match('/^\s*@\w+/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            $comment = trim(substr($comment, 0, $matches[0][1]));
        }
        if ($comment !== '') {
            return rtrim(Console::renderColoredString(Console::markdownToAnsi($comment)));
        }

        return '';
    }

    /**
     * Get class PHPDoc parsed
     * @param object $object
     * @param string $parseMethod
     * @return string|array|null
     */
    protected static function getClassDoc($object, $parseMethod = 'parseDocCommentSummary') {
        try {
            $reflection = new \ReflectionClass($object);
            $docData = static::$parseMethod($reflection);
        } catch (\ReflectionException $exception) {
            $docData = null;
        }
        return $docData;
    }

    /**
     * Get method or property PHPDoc parsed
     * @param object $object
     * @param string $method
     * @param string $type
     * @param string $parseMethod
     * @return string|array|null
     */
    protected static function getMethodPropertyDoc($object, $method, $type = self::REFLECTION_METHOD, $parseMethod = 'parseDocCommentSummary') {
        try {
            $reflection = $type === static::REFLECTION_METHOD
                ? new \ReflectionMethod($object, $method)
                : new \ReflectionProperty($object, $method);
            $docData = static::$parseMethod($reflection);
        } catch (\ReflectionException $exception) {
            $docData = null;
        }
        return $docData;
    }
}