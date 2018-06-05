<?php

namespace Reaction\Base;

/**
 * StaticInstanceTrait provides methods to satisfy [[StaticInstanceInterface]] interface.
 *
 * @see StaticInstanceInterface
 */
trait StaticInstanceTrait
{
    /**
     * @var static[] static instances in format: `[className => object]`
     */
    private static $_instances = [];


    /**
     * Returns static class instance, which can be used to obtain meta information.
     * @param bool $refresh whether to re-create static instance even, if it is already cached.
     * @return static class instance.
     */
    public static function instance($refresh = false)
    {
        $className = get_called_class();
        if ($refresh || !isset(self::$_instances[$className])) {
            self::$_instances[$className] = \Reaction::create($className);
        }
        return self::$_instances[$className];
    }
}
