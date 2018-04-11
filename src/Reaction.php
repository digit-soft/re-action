<?php

/**
 * Class Reaction. Base static class
 */
class Reaction
{
    const APP_ENV_PROD = 'production';
    const APP_ENV_DEV  = 'development';

    /** @var \DI\Container */
    public static $di;
    /** @var \Reaction\BaseApplicationInterface */
    public static $app;

    /** @var string */
    protected static $configsPath;

    /**
     * Initialize whole application
     * @param string $configsPath
     */
    public static function init($configsPath = null) {
        if(!isset($configsPath)) {
            throw new \Reaction\Exceptions\InvalidArgumentException("Missing \$configsPath option");
        }
        static::$configsPath = $configsPath;
        $config = static::getConfigReader();
        static::initContainer($config->data);
        static::initApp();
    }

    /**
     * Create instance of class
     * @param string|array $type
     * @param array $params
     * @return mixed
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    public static function create($type, array $params = []) {
        $class = null;
        $config = [];

        if (is_string($type)) {
            $class = $type;
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);
            $config = $type;
        } elseif (is_callable($type, true)) {
            return static::call($type, $params);
        } elseif (is_array($type)) {
            throw new \Reaction\Exceptions\InvalidConfigException('Object configuration must be an array containing a "class" element.');
        }

        if(isset($class)) {
            $object = static::$di->make($class, $params);
            if(!empty($config)) static::configure($object, $config);
            return $object;
        } else {
            throw new \Reaction\Exceptions\InvalidConfigException('Unsupported configuration type: ' . gettype($type));
        }
    }

    /**
     * Call closure with parameters using DI
     * @param callable $callable
     * @param array $params
     * @return mixed
     */
    public static function call($callable, $params = []) {
        return static::$di->call($callable, $params);
    }

    /**
     * Configures an object with the initial property values.
     * @param \object $object the object to be configured
     * @param array $properties the property initial values given in terms of name-value pairs.
     * @return \object the object itself
     */
    public static function configure($object, $properties)
    {
        foreach ($properties as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }

    /**
     * Get instance of config reader
     * @return \Reaction\Base\ConfigReader
     */
    protected static function getConfigReader() {
        $conf = [
            'path' => static::$configsPath,
        ];
        return new \Reaction\Base\ConfigReader($conf);
    }

    /**
     * Initialize DI container
     * @param array $config
     */
    protected static function initContainer($config = []) {
        $builder = new DI\ContainerBuilder();
        $builder->useAnnotations(true)->useAutowiring(true);
        $builder->addDefinitions($config);
        static::$di = $builder->build();
    }

    /**
     * Initialize application object
     */
    protected static function initApp() {
        static::$app = static::create(\Reaction\BaseApplicationInterface::class);
    }
}