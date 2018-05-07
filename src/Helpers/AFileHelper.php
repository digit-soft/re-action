<?php

namespace Reaction\Helpers;

use React\Filesystem\FilesystemInterface;
use React\Filesystem\Node\Directory;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\File;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\GenericOperationInterface;
use React\Stream\WritableStreamInterface;

/**
 * Class AFileHelper. Async file helper
 * @package Reaction\Helpers
 */
class AFileHelper
{
    /** @var string File system component key in Application */
    public static $fileSystem = 'fs';
    /** @var string File open mode (default to 0755 / rwxr-xr-x) */
    public static $fileCreateMode = 0755;

    /**
     * Get file object
     * @param string $filePath
     * @return FileInterface|File
     */
    public static function file($filePath)
    {
        $filePath = \Reaction::$app->getAlias($filePath);
        $filePath = FileHelper::normalizePath($filePath);
        return static::getFs()->file($filePath);
    }

    /**
     * Get dir object
     * @param string $dirPath
     * @return DirectoryInterface|Directory
     */
    public static function dir($dirPath)
    {
        $dirPath = \Reaction::$app->getAlias($dirPath);
        $dirPath = FileHelper::normalizePath($dirPath);
        return static::getFs()->dir($dirPath);
    }

    /**
     * Check that file or directory exists
     * @param string|FileInterface|DirectoryInterface $filePath
     * @return \React\Promise\PromiseInterface
     */
    public static function exists($filePath)
    {
        $file = static::ensureFileObject($filePath);
        return $file->exists();
    }

    /**
     * Open file with given flags
     * @param string $filePath
     * @param string $flags c - create, w - write, r - read, t - truncate
     * @return \React\Promise\PromiseInterface
     */
    public static function open($filePath, $flags = 'r')
    {
        $file = static::ensureFileObject($filePath);
        $createMode = FileHelper::permissionsAsString(static::$fileCreateMode);
        return $file->open($flags, $createMode);
    }

    /**
     * Put contents into file
     * @param string|File $filePath
     * @param string      $contents
     * @param string      $openMode
     * @return \React\Promise\PromiseInterface
     */
    public static function putContents($filePath, $contents, $openMode = 'cw')
    {
        $file = static::ensureFileObject($filePath);
        $createMode = FileHelper::permissionsAsString(static::$fileCreateMode);
        return $file->open($openMode, $createMode)->then(function (WritableStreamInterface $stream) use ($file, $contents) {
            $stream->write($contents);
            return $file->close();
        });
    }

    /**
     * Get file contents
     * @param string|File $filePath
     * @return \React\Promise\PromiseInterface
     */
    public static function getContents($filePath)
    {
        $file = static::ensureFileObject($filePath);
        return $file->exists()->then(
            function () use ($file) {
                return $file->getContents();
            }
        );
    }

    /**
     * Perform 'chmod' operation
     * @param string|GenericOperationInterface $path File or Directory
     * @param int                              $mode
     * @return \React\Promise\PromiseInterface
     */
    public static function chmod($path, $mode = 0755)
    {
        if ($path instanceof GenericOperationInterface) {
            return $path->chmod($mode);
        }
        return static::file($path)->chmod($mode);
    }

    /**
     * Perform 'chmod' operation recursive for directory
     * @param string|DirectoryInterface $dirPath
     * @param int                       $mode
     * @return \React\Promise\PromiseInterface
     */
    public static function chmodRecursive($dirPath, $mode = 0755)
    {
        $dir = static::ensureDirObject($dirPath);
        return $dir->chmodRecursive($mode);
    }

    /**
     * Create directory
     * @param string $dirPath
     * @param int    $mode
     * @param bool   $recursive
     * @return \React\Promise\PromiseInterface
     */
    public static function createDir($dirPath, $mode = null, $recursive = true)
    {
        $dir = static::ensureDirObject($dirPath);
        $file = static::ensureFileObject($dirPath);
        if ($mode === null) {
            $mode = static::$fileCreateMode;
        }
        return $file->exists()->then(
            null,
            function () use ($dir, $mode, $recursive) {
                $createMode = FileHelper::permissionsAsString($mode);
                return $recursive ? $dir->createRecursive($createMode) : $dir->create($mode);
            }
        );
    }

    /**
     * Ensure that object is implementing FileInterface
     * @param File|Directory|string $pathOrObject
     * @return File
     */
    protected static function ensureFileObject($pathOrObject)
    {
        if ($pathOrObject instanceof FileInterface) {
            return $pathOrObject;
        } elseif ($pathOrObject instanceof DirectoryInterface) {
            return static::file($pathOrObject->getPath());
        } elseif (is_string($pathOrObject)) {
            return static::file($pathOrObject);
        }
        return $pathOrObject;
    }

    /**
     * Ensure that object is implementing DirectoryInterface
     * @param FileInterface|DirectoryInterface|string $pathOrObject
     * @return DirectoryInterface
     */
    protected static function ensureDirObject($pathOrObject)
    {
        if ($pathOrObject instanceof DirectoryInterface) {
            return $pathOrObject;
        } elseif ($pathOrObject instanceof FileInterface) {
            return static::dir($pathOrObject->getPath());
        } elseif (is_string($pathOrObject)) {
            return static::dir($pathOrObject);
        }
        return $pathOrObject;
    }

    /**
     * Get FilesystemInterface object
     * @return FilesystemInterface
     */
    protected static function getFs()
    {
        return \Reaction::$app->{static::$fileSystem};
    }
}