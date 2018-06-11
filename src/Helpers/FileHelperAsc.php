<?php

namespace Reaction\Helpers;

use React\EventLoop\Timer\TimerInterface;
use React\Filesystem\FilesystemInterface;
use React\Filesystem\Node\Directory;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\File;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\GenericOperationInterface;
use React\Stream\WritableStreamInterface;
use Reaction\Promise\Deferred;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\Promise;
use function Reaction\Promise\resolve;

/**
 * Class AFileHelper. Async file helper
 * @package Reaction\Helpers
 */
class FileHelperAsc
{
    /** @var string File system component key in Application */
    public static $fileSystem = 'fs';
    /** @var string File open mode (default to 0755 / rwxr-xr-x) */
    public static $fileCreateMode = 0755;
    /** @var int File lock timeout */
    public static $LockTimeout = 10;
    /** @var array Files those were locked by user for writing */
    protected static $_lockedFiles = [];
    /** @var Deferred[][] Files those were locked by user for writing */
    protected static $_lockedFilesQueue = [];
    /** @var TimerInterface Timer used to unlock "forgotten" files  */
    protected static $_unlockTimer;

    /**
     * Get file object
     * @param string $filePath
     * @return FileInterface|File
     */
    public static function file(&$filePath)
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
    public static function dir(&$dirPath)
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
     * Create a file
     * @param string $filePath
     * @param int|null   $mode
     * @param int|null   $time
     * @return \React\Promise\PromiseInterface
     */
    public static function create($filePath, $mode = null, $time = null)
    {
        $mode = isset($mode) ? $mode : static::$fileCreateMode;
        $modeStr = static::permissionsAsString($mode);
        $time = is_int($time) ? $time : time();
        $file = static::file($filePath);
        return $file->create($modeStr, $time)
            ->then(function() use ($file, $mode) {
                return $file->chmod($mode);
            });
    }

    /**
     * Put contents into file
     * @param string|File $filePath
     * @param string      $contents
     * @param string      $openFlags
     * @param bool|int    $lock Lock file for operation and check for a lock OR lock timeout
     * @param int|null    $createMode
     * @return ExtendedPromiseInterface
     */
    public static function putContents($filePath, $contents, $openFlags = 'cwt', $lock = true, $createMode = null)
    {
        $lockTimeout = is_int($lock) && !empty($lock) ? $lock : null;
        $lock = !empty($lock);
        $file = static::ensureFileObject($filePath);
        $createMode = isset($createMode) ? $createMode : static::$fileCreateMode;
        $createModeStr = FileHelper::permissionsAsString($createMode);
        $unlockCallback = function ($result = null) use ($filePath, $lock) {
            if ($lock) {
                static::unlock($filePath);
            }
            if (is_object($result) && $result instanceof \Throwable) {
                throw $result;
            }
            return $result;
        };
        return static::onUnlock($filePath)
            ->then(function() use (&$file, $filePath, $lockTimeout, $openFlags, $createModeStr, $lock) {
                if ($lock) {
                    static::lock($filePath, $lockTimeout);
                }
                return $file->open($openFlags, $createModeStr);
            })->then(function(WritableStreamInterface $stream) use (&$file, $contents) {
                $stream->write($contents);
                return $file->close();
            })->then($unlockCallback, $unlockCallback);
    }

    /**
     * Get file contents
     * @param string|File $filePath
     * @param bool|int    $lock Lock file for operation OR lock timeout
     * @return ExtendedPromiseInterface
     */
    public static function getContents($filePath, $lock = true)
    {
        $lockTimeout = is_int($lock) && !empty($lock) ? $lock : null;
        $lock = !empty($lock);
        $file = static::ensureFileObject($filePath);
        $unlockCallback = function ($result = null) use ($lock, $filePath) {
            if ($lock) {
                static::unlock($filePath);
            }
            if (is_object($result) && $result instanceof \Throwable) {
                throw $result;
            }
            return $result;
        };
        return static::onUnlock($filePath)->then(
            function () use (&$file, $filePath, $lock, $lockTimeout) {
                if ($lock) {
                    static::lock($filePath, $lockTimeout);
                }
                return $file->exists();
            }
        )
            ->then(function () use (&$file, $filePath) {
                return $file->getContents();
            })->then($unlockCallback, $unlockCallback);
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
        return $file->exists()
            ->then(null, function() use ($dir, $mode, $recursive) {
                $createMode = FileHelper::permissionsAsString($mode);
                return $recursive ? $dir->createRecursive($createMode) : $dir->create($mode);
            })->then(function() use ($dir, $mode) {
                return $dir->chmod($mode);
            });
    }

    /**
     * Lock file for some time or until some operation is not finished
     * @param string $filePath
     * @param int $timeout
     */
    public static function lock($filePath, $timeout = null)
    {
        $filePath = FileHelper::normalizePath($filePath);
        $timeout = isset($timeout) ? $timeout : static::$LockTimeout;
        $expire = time() + $timeout;
        if (isset(static::$_lockedFiles[$filePath])) {
            $expire = static::$_lockedFiles[$filePath] <= $expire ? $expire : static::$_lockedFiles[$filePath];
        }
        static::$_lockedFiles[$filePath] = $expire;
        static::checkUnlockTimer();
    }

    /**
     * Unlock file
     * @param string $filePath
     * @param bool   $processQueue
     */
    public static function unlock($filePath, $processQueue = true)
    {
        $filePath = FileHelper::normalizePath($filePath);
        if (isset(static::$_lockedFiles[$filePath])) {
            unset(static::$_lockedFiles[$filePath]);
        }
        if ($processQueue) {
            static::processUnlockQueue($filePath);
        }
    }

    /**
     * Check file is locked or not
     * @param string $filePath
     * @return bool
     */
    public static function isLocked($filePath)
    {
        $filePath = FileHelper::normalizePath($filePath);
        return isset(static::$_lockedFiles[$filePath]);
    }

    /**
     * Get promise of unlocking file
     * @param string $filePath
     * @return ExtendedPromiseInterface
     */
    public static function onUnlock($filePath) {
        $filePath = FileHelper::normalizePath($filePath);
        if (!static::isLocked($filePath)) {
            return resolve(true);
        }
        $deferred = new Deferred();
        if (!isset(static::$_lockedFilesQueue[$filePath])) {
            static::$_lockedFilesQueue[$filePath] = [];
        }
        static::$_lockedFilesQueue[$filePath][] = $deferred;
        return $deferred->promise();
    }

    /**
     * Convert octal permissions to string
     * @param int $modeOctal Octal mode (0755). Do not omit zero, must be octal, not decimal!
     * @return string String permissions representation (rwxr-xr-x)
     */
    public static function permissionsAsString($modeOctal) {
        return FileHelper::permissionsAsString($modeOctal);
    }

    /**
     * Convert string representation of permissions to octal
     * @param string $modeStr String permissions (rwxr-xr-x)
     * @return int Octal permissions representation
     */
    public static function permissionsAsOctal($modeStr) {
        return FileHelper::permissionsAsOctal($modeStr);
    }

    /**
     * Process unlock waiting deferred(s) (and promises)
     * @param string $filePath
     */
    protected static function processUnlockQueue($filePath) {
        if (empty(static::$_lockedFilesQueue[$filePath])) {
            return;
        }
        while (!empty(static::$_lockedFilesQueue[$filePath])) {
            if (static::isLocked($filePath)) {
                break;
            }
            /** @var Deferred $deferred */
            $deferred = array_shift(static::$_lockedFilesQueue[$filePath]);
            $deferred->resolve(true);
        }
    }

    /**
     * Ensure that object is implementing FileInterface
     * @param File|Directory|string $pathOrObject
     * @return File
     */
    protected static function ensureFileObject(&$pathOrObject)
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
    protected static function ensureDirObject(&$pathOrObject)
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
     * Check if unlock timer is set or create it
     */
    protected static function checkUnlockTimer() {
        if (static::$_unlockTimer instanceof TimerInterface) {
            return;
        }
        static::$_unlockTimer = \Reaction::$app->loop->addPeriodicTimer(1, function () {
            $now = time();
            foreach (static::$_lockedFiles as $filePath => $timeout) {
                if ($timeout <= $now) {
                    static::unlock($filePath);
                }
            }
        });
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