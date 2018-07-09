<?php

use Reaction\Exceptions\InvalidConfigException;
use Reaction\StaticApplicationInterface;

/**
 * Class Reaction. Base static class
 */
class Reaction
{
    const APP_ENV_PROD = 'production';
    const APP_ENV_DEV  = 'development';

    /** @var Composer\Autoload\ClassLoader */
    public static $composer;
    /** @var \Reaction\DI\Container */
    public static $di;
    /** @var StaticApplicationInterface */
    public static $app;
    /** @var \Reaction\Base\ConfigReader */
    public static $config;
    /** @var \Reaction\Base\AnnotationsReader */
    public static $annotations;

    /** @var string */
    protected static $configsPath;
    /** @var string */
    protected static $appType;

    /**
     * Initialize whole application
     * @param \Composer\Autoload\ClassLoader $composer
     * @param string                         $configsPath Path where to look for config files
     * @param string                         $appType Application type (web|console)
     */
    public static function init(Composer\Autoload\ClassLoader $composer = null, $configsPath = null, $appType = StaticApplicationInterface::APP_TYPE_WEB)
    {
        static::initBasic($composer, $configsPath, $appType);
        static::initStaticApp();
    }

    /**
     * Initialize basic components
     * @param \Composer\Autoload\ClassLoader|null $composer
     * @param string|null                         $configsPath
     * @param string                              $appType
     */
    public static function initBasic(Composer\Autoload\ClassLoader $composer = null, $configsPath = null, $appType = StaticApplicationInterface::APP_TYPE_WEB)
    {
        if (!isset($composer)) {
            $composer = static::locateClassLoader();
        }
        if (!isset($configsPath)) {
            $configsPath = static::locateConfigsPath();
        }
        if (!isset($composer)) {
            throw new \Reaction\Exceptions\InvalidArgumentException("Missing \$composer option");
        }
        if (!isset($configsPath)) {
            throw new \Reaction\Exceptions\InvalidArgumentException("Missing \$configsPath option");
        }
        static::$composer = $composer;
        static::$configsPath = $configsPath;
        static::$appType = $appType;
        static::initConfigReader();
        static::initAnnotationReader();
        static::initContainer();
    }

    /**
     * Locate configs path
     * @return null|string
     */
    protected static function locateConfigsPath()
    {
        $cwd = getcwd();
        $defaultDir = DIRECTORY_SEPARATOR . 'Config';
        $path = $cwd . $defaultDir;
        if (!file_exists($path) || !is_dir($path)) {
            return null;
        }
        return $path;
    }

    /**
     * Get composer class loader
     * @return null|\Composer\Autoload\ClassLoader
     */
    protected static function locateClassLoader()
    {
        $cwd = getcwd();
        $path = $cwd . DIRECTORY_SEPARATOR . 'vendor/autoload.php';
        if (!file_exists($path) || !is_file($path)) {
            return null;
        }
        return include $path;
    }

    /**
     * Create instance of class without throwing exceptions
     * @param string|array $type
     * @param array $params
     * @return mixed|null
     */
    public static function createNoExc($type, array $params = [])
    {
        try {
            return static::create($type, $params);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Create instance of class
     * @param string|array $type Class name or indexed parameters array with key "class"
     * @param array        $params Constructor parameters indexed array
     * @return object|mixed
     * @throws InvalidConfigException
     */
    public static function create($type, array $params = [])
    {
        if (is_string($type)) {
            return static::$di->get($type, $params);
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);
            return static::$di->get($class, $params, $type);
        } elseif (is_callable($type, true)) {
            return static::$di->invoke($type, $params);
        } elseif (is_array($type)) {
            throw new InvalidConfigException('Object configuration must be an array containing a "class" element.');
        }

        throw new InvalidConfigException('Unsupported configuration type: ' . gettype($type));
    }

    /**
     * Call closure with parameters using DI
     * @param callable $callable
     * @param array    $params
     * @return mixed
     * @throws InvalidConfigException
     * @throws \Reaction\Exceptions\NotInstantiableException
     */
    public static function invoke($callable, $params = [])
    {
        return static::$di->invoke($callable, $params);
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
     * Translates a message to the specified language.
     *
     * @param string $category Message category
     * @param string $message  Message for translation
     * @param array  $params   Parameters array
     * @param string $language Language translate to
     * @return string
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        if (static::$app !== null && static::$app->getI18n() !== null) {
            return static::$app->getI18n()->translate($category, $message, $params, $language ?: static::$app->language);
        }
        $_params = [];
        foreach ($params as $key => $value) { $_params['{' . $key . '}'] = $value; }
        return !empty($params) ? strtr($message, $_params) : $message;
    }

    /**
     * Translates a message to the specified language.
     * Looks like a ::t() method but returns a TranslationPromise for further translation when request language will known
     *
     * @param string $category Message category
     * @param string $message  Message for translation
     * @param array  $params   Parameters array
     * @param string $language Language translate to
     * @return \Reaction\I18n\TranslationPromise
     */
    public static function tp($category, $message, $params = [], $language = null)
    {
        $translationPromise = new \Reaction\I18n\TranslationPromise($category, $message, $params, $language);
        return $translationPromise;
    }

    /**
     * Returns a string representing the current version of framework.
     * @return string the version of Reaction framework
     */
    public static function getVersion()
    {
        return '0.2.4';
    }

    /**
     * Check that application type is web
     * @return bool
     */
    public static function isWebApp()
    {
        return static::$appType === StaticApplicationInterface::APP_TYPE_WEB;
    }

    /**
     * Check that application type is console
     * @return bool
     */
    public static function isConsoleApp()
    {
        return static::$appType === StaticApplicationInterface::APP_TYPE_CONSOLE;
    }

    /**
     * Shortcut function to check that Application is using DEVELOPMENT environment
     * @return bool
     */
    public static function isDev()
    {
        return empty(Reaction::$app) || Reaction::$app->envType === StaticApplicationInterface::APP_ENV_DEV;
    }

    /**
     * Shortcut function to check that Application is using PRODUCTION environment
     * @return bool
     */
    public static function isProd()
    {
        return Reaction::$app && Reaction::$app->envType === StaticApplicationInterface::APP_ENV_PROD;
    }

    /**
     * Shortcut function to check that Application is in DEBUG mode
     * @return bool
     */
    public static function isDebug()
    {
        return Reaction::$app && Reaction::$app->debug;
    }

    /**
     * Shortcut function to check that Application is in TESTING mode
     * @return bool
     */
    public static function isTest()
    {
        return Reaction::$app && Reaction::$app->test;
    }

    /**
     * Shortcut to Reaction::$app->logger->debug()
     * @param string|mixed $message
     * @param array $context
     */
    public static function debug($message, $context = [])
    {
        static::$app->logger->debug($message, $context, 1);
    }

    /**
     * Shortcut to Reaction::$app->logger->info()
     * @param string|mixed $message
     * @param array $context
     */
    public static function info($message, $context = [])
    {
        static::$app->logger->info($message, $context, 1);
    }

    /**
     * Shortcut to Reaction::$app->logger->warning()
     * @param string|mixed $message
     * @param array $context
     */
    public static function warning($message, $context = [])
    {
        static::$app->logger->warning($message, $context, 1);
    }

    /**
     * Shortcut to Reaction::$app->logger->error()
     * @param string|mixed $message
     * @param array $context
     */
    public static function error($message, $context = [])
    {
        static::$app->logger->error($message, $context, 1);
    }

    /**
     * Shortcut to Reaction::$app->logger->profile()
     * @param string|null $message
     * @param string|null $endId
     * @return string|null
     */
    public static function profile($message = null, $endId = null)
    {
        return static::$app->logger->profile($message, $endId, 1);
    }

    /**
     * Shortcut to Reaction::$app->logger->profileEnd()
     * @param string|null $endId
     * @param string|null $message
     */
    public static function profileEnd($endId = null, $message = null)
    {
        static::$app->logger->profileEnd($endId, $message, 1);
    }

    /**
     * Shortcut to Reaction::$app->getAlias()
     * @param string $alias
     * @param bool   $throwException
     * @return bool|string
     */
    public static function getAlias($alias, $throwException = true)
    {
        return Reaction::$app->getAlias($alias, $throwException);
    }

    /**
     * Shortcut to Reaction::$app->setAlias()
     * @param string $alias
     * @param string $path
     */
    public static function setAlias($alias, $path)
    {
        Reaction::$app->setAlias($alias, $path);
    }

    /**
     * Get instance of config reader
     * @param bool $flush
     * @return \Reaction\Base\ConfigReader
     */
    protected static function getConfigReader($flush = false)
    {
        if ($flush || !isset(static::$config)) {
            $conf = [
                'path' => static::$configsPath,
                'appType' => static::$appType,
            ];
            static::$config = new \Reaction\Base\ConfigReader($conf);
        }
        return static::$config;
    }

    /**
     * Initialize DI container
     */
    protected static function initContainer()
    {
        $config = static::getConfigReader()->get('container');
        static::$di = new \Reaction\DI\Container($config);
    }

    /**
     * Initialize application object
     */
    public static function initStaticApp()
    {
        $config = static::getConfigReader()->get('appStatic');
        $config['class'] = StaticApplicationInterface::class;
        //Use config after creation, so Reaction::$app will be available inside StaticApplicationInterface components
        $appLateConfig = [];
        $appLateConfigKeys = ['components'];
        foreach ($appLateConfigKeys as $appKey) {
            if (isset($config[$appKey])) {
                $appLateConfig[$appKey] = $config[$appKey];
                unset($config[$appKey]);
            }
        }
        static::$app = static::create($config);
        if (!empty($appLateConfig)) {
            static::configure(static::$app, $appLateConfig);
        }
    }

    /**
     * Initialize config reader
     */
    protected static function initConfigReader()
    {
        static::getConfigReader();
    }

    /**
     * Initialize annotation reader
     */
    protected static function initAnnotationReader()
    {
        static::$annotations = new \Reaction\Base\AnnotationsReader();
    }

    /**
     * Print backtrace
     * @param bool $withArgs Show arguments names and content
     * @param bool $useEcho Use direct call to `echo` function instead of Stream::write()
     */
    public static function dbg($withArgs = false, $useEcho = false)
    {
        \Reaction\Base\Logger\Debugger::backTrace($withArgs, 1, $useEcho);
    }
}