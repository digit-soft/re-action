<?php

namespace Reaction\Helpers;

/**
 * Class ClassFinder
 * @package Reaction\Helpers
 */
class ClassFinderHelper
{
    const DS = DIRECTORY_SEPARATOR; //Directory separator
    const CS = '\\';                //Class namespace separator

    /**
     * Find class names inside given namespace
     * @param string|array $namespace
     * @param bool         $recursively Search recursively is namespace directory
     * @param bool         $withoutAutoload Do not check class names through 'class_exist(name, true)'
     * @return array
     */
    public static function findClassesPsr4($namespace, $recursively = false, $withoutAutoload = false) {
        $classNames = [];
        if(is_array($namespace)) {
            foreach ($namespace as $ns) {
                $classNames = ArrayHelper::merge($classNames, static::findClassesPsr4($ns, $recursively, $withoutAutoload));
            }
            return $classNames;
        }
        $namespace = trim($namespace, static::CS);
        list($nsPrefix, $namespacePaths) = static::getNamespacePaths($namespace);
        if (empty($namespacePaths)) {
            return [];
        }
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
                    if (!preg_match($regEx, $fileStr)) {
                        continue;
                    }
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

        for ($i = 0; $i < count($files); $i++) {
            $fileStr = $files[$i];
            $className = $namespace . static::CS . str_replace(static::DS, static::CS, $fileStr);
            if ($withoutAutoload || class_exists($className, true)) {
                $classNames[] = $className;
            }
        }
        return $classNames;
    }

    /**
     * Get namespace directory path
     * @param string $namespace
     * @param bool $first
     * @return array|mixed|null|string
     */
    public static function getNamespacePath($namespace, $first = true)
    {
        $namespace = trim($namespace, static::CS);
        $nsPrefixes = static::getNamespacePrefixes($namespace);
        $loaderPrefixes = static::getLoaderPrefixes();
        $nsSuffix = '';
        foreach ($nsPrefixes as $prefix) {
            if (!isset($loaderPrefixes[$prefix])) {
                continue;
            }
            $nsSuffix = trim(mb_substr($namespace, mb_strlen($prefix)), static::CS);
            $paths = $loaderPrefixes[$prefix];
            break;
        }
        if (!isset($paths)) {
            return $first ? null : [];
        }
        $results = [];
        foreach ($paths as $path) {
            $result = $path . static::DS . str_replace('\\', static::DS, $nsSuffix);
            if (!file_exists($result) || !is_dir($result)) {
                continue;
            }
            if ($first) {
                return $result;
            }
            $results[] = $result;
        }
        return $results;
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
        $nsPrefixesCount = count($nsPrefixes);
        $loaderPrefixes = static::getLoaderPrefixes();
        $nsPrefix = null;
        for ($i = 0; $i < $nsPrefixesCount; $i++) {
            $nsPrefix = $nsPrefixes[$i];
            if (!isset($loaderPrefixes[$nsPrefix])) {
                continue;
            }
            if ($nsPrefix === $namespace) {
                return $loaderPrefixes[$nsPrefix];
            }
            $nsSuffix = trim(substr($namespace, strlen($nsPrefix)), static::CS);
            $nsSuffix = str_replace(static::CS, static::DS, $nsSuffix);
            $loaderPaths = $loaderPrefixes[$nsPrefix];
            $loaderPathsCount = count($loaderPaths);
            for ($j = 0; $j < $loaderPathsCount; $j++) {
                $possiblePath = $loaderPaths[$j] . static::DS . $nsSuffix;
                if(file_exists($possiblePath) && is_dir($possiblePath)) { $paths[] = $possiblePath; }
            }
            if (!empty($paths)) {
                break;
            }
        }
        if (empty($paths)) {
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
        $namespaceExpCount = count($namespaceExp);
        if ($namespaceExpCount === 1) {
            return $namespaceExp;
        }
        $prefixes = [];
        $currPrefix = '';
        for ($i = 0; $i < $namespaceExpCount; $i++) {
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
}