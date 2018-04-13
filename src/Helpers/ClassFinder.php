<?php

namespace Reaction\Helpers;

/**
 * Class ClassFinder
 * @package Reaction\Helpers
 */
class ClassFinder
{
    const DS = DIRECTORY_SEPARATOR; //Directory separator
    const CS = '\\';                //Class namespace separator

    /**
     * Find class names inside given namespace
     * @param string|array $namespace
     * @param bool         $recursively
     * @return array
     */
    public static function findClassesPsr4($namespace, $recursively = false) {
        $namespace = trim($namespace, static::CS);
        list($nsPrefix, $namespacePaths) = static::getNamespacePaths($namespace);
        if(empty($namespacePaths)) return [];
        $files = [];
        $regEx = '/\.php$/';
        for ($i = 0; $i < count($namespacePaths); $i++) {
            $dir = $namespacePaths[$i];
            $dirIt = new \RecursiveDirectoryIterator($dir);
            $files = [];
            if($recursively) {
                foreach(new \RecursiveIteratorIterator($dirIt) as $file)
                {
                    $fileStr = (string)$file;
                    if(!preg_match($regEx, $fileStr)) continue;
                    $fileStr = substr($fileStr, strlen($dir) + 1, -4);
                    $files[] = $fileStr;
                }
            } else {
                $_files = glob($dir . static::DS . '*.php');
                for ($j = 0; $j < count($_files); $j++) {
                    $fileStr = $_files[$j];
                    $fileStr = substr($fileStr, strlen($dir) + 1, -4);
                    $files[] = $fileStr;
                }
            }
        }

        $classNames = [];
        for ($i = 0; $i < count($files); $i++) {
            $fileStr = $files[$i];
            $className = $namespace . static::CS . str_replace(static::DS, static::CS, $fileStr);
            if(class_exists($className)) $classNames[] = $className;
        }
        return $classNames;
    }

    /**
     * Get possible namespace paths
     * @param string $namespace
     * @return array|mixed
     */
    protected static function getNamespacePaths($namespace) {
        $paths = [];
        $namespace = trim($namespace, static::CS);
        $nsPrefixes = static::getNamespacePrefixes($namespace);
        $loaderPrefixes = static::getLoaderPrefixes();
        $nsPrefix = null;
        for ($i = 0; $i < count($nsPrefixes); $i++) {
            $nsPrefix = $nsPrefixes[$i];
            if(!isset($loaderPrefixes[$nsPrefix])) continue;
            if($nsPrefix === $namespace) return $loaderPrefixes[$nsPrefix];
            $nsSuffix = trim(substr($namespace, strlen($nsPrefix)), static::CS);
            $nsSuffix = str_replace(static::CS, static::DS, $nsSuffix);
            $loaderPaths = $loaderPrefixes[$nsPrefix];
            for ($j = 0; $j < count($loaderPaths); $j++) {
                $possiblePath = $loaderPaths[$j] . static::DS . $nsSuffix;
                if(file_exists($possiblePath) && is_dir($possiblePath)) { $paths[] = $possiblePath; }
            }
            if(!empty($paths)) break;
        }
        if(empty($paths)) {
            return [null, []];
        }

        return [$nsPrefix, $paths];
    }

    /**
     * Explode namespace to possible prefixes
     * @param string $namespace
     * @return array
     */
    protected static function getNamespacePrefixes($namespace) {
        $namespaceExp = explode(static::CS, trim($namespace, static::CS));
        if(count($namespaceExp) === 1) return $namespaceExp;
        $prefixes = [];
        $currPrefix = '';
        for ($i = 0; $i < count($namespaceExp); $i++) {
            $currPrefix .= static::CS . $namespaceExp[$i];
            $prefixes[] = trim($currPrefix, static::CS);
        }
        $prefixes = array_reverse($prefixes);
        return $prefixes;
    }

    /**
     * Get prefixes from loader
     * @return array
     */
    protected static function getLoaderPrefixes() {
        $loader = static::getLoader();
        $_prefixes = [
            [],
            $loader->getPrefixesPsr4(),
        ];
        $_prefixes = ArrayHelper::merge(...$_prefixes);
        $prefixes = [];
        foreach ($_prefixes as $prefix => $paths) {
            $prefix = trim($prefix, '\\');
            $_paths = [];
            for ($i = 0; $i < count($paths); $i++) { $_paths[] = realpath($paths[$i]); }
            $prefixes[$prefix] = $_paths;
        }
        return $prefixes;
    }

    /**
     * Get class loader
     * @return \Composer\Autoload\ClassLoader
     */
    protected static function getLoader() {
        return \Reaction::$composer;
    }

    /**
     * Get logger
     * @return \Psr\Log\AbstractLogger
     */
    protected static function getLogger() {
        return \Reaction::$app->logger;
    }
}