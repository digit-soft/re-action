<?php

namespace Reaction\Web\Sessions;

use React\Filesystem\Node\File;
use React\Filesystem\Node\FileInterface;
use React\Stream\WritableStreamInterface;
use Reaction\Base\BaseObject;
use Reaction\Exceptions\SessionException;
use Reaction\Helpers\FileHelperAsc;
use Reaction\Helpers\Json;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * Class SessionArchiveInFiles
 * @package Reaction\Web\Sessions
 */
class SessionArchiveInFiles extends BaseObject implements SessionArchiveInterface
{
    /**
     * @var string Session archive path
     */
    public $archivePath = '@runtime/session_archive';
    /**
     * @var string|null Archive path processed
     */
    protected $_archivePath;

    /**
     * Get data from archive
     * @param string $id Session id
     * @param bool   $remove Flag to remove data
     * @return ExtendedPromiseInterface with data array
     */
    public function get($id, $remove = true)
    {
        $self = $this;
        /** @var File $file */
        $file = null;
        $getPromise = $this->getArchiveFilePath($id, true)->then(
            function($filePath) use (&$file) {
                $file = \Reaction::$app->fs->file($filePath);
                return $file->exists()->then(
                    function() use (&$file) {
                        return $file->open('r');
                    }
                );
            }
        )->then(
            function($stream) use (&$file) {
                /** @var \React\Filesystem\Stream\ReadableStream $stream */
                return \React\Promise\Stream\buffer($stream)->then(
                    function($data) use (&$file) {
                        $file->close();
                        return $data;
                    },
                    function($error) use (&$file) {
                        $file->close();
                        return reject($error);
                    }
                );
            }
        )->then(
            function($dataStr) use ($self, &$file, $remove) {
                try {
                    $data = $self->unserializeData($dataStr);
                } catch (\InvalidArgumentException $exception) {
                    $data = null;
                }
                $data = is_array($data) ? $data : null;
                if ($remove) {
                    $callback = function() use ($data) { return $data; };
                    return $file->remove()->then($callback, $callback);
                }
                return $data;
            },
            function() use ($id) {
                $message = sprintf('Failed to restore session "%s"', $id);
                throw new SessionException($message);
            }
        );
        return resolve($getPromise);
    }

    /**
     * Save data to archive
     * @param string $id
     * @param array  $data
     * @return ExtendedPromiseInterface which resolved after save complete
     */
    public function set($id, $data)
    {
        $dataSr = $this->serializeData($data);
        /** @var File $file */
        $file = null;
        $createMode = FileHelperAsc::permissionsAsString(0777);
        $writePromise = $this->getArchiveFilePath($id)->then(
            function($filePath) use (&$file) {
                $file = \Reaction::$app->fs->file($filePath);
                return \Reaction::$app->fs->file($filePath)->exists();
            }
        )->then(null,
            function() use (&$file, $createMode) { return $file->create($createMode); }
        )->then(
            function() use (&$file) { return $file->open('wf'); }
        )->then(
            function(WritableStreamInterface $stream) use (&$file, $dataSr) {
                $stream->write($dataSr);
                $stream->close();
                return $file->close();
            }
        )->then(null, function($error = null) {
            $message = $error instanceof \Throwable
                ? $error->getMessage() . "\n" . $error->getFile() . ' #' . $error->getLine() : $error;
            \Reaction::error($message);
            return false;
        });
        return resolve($writePromise);
    }

    /**
     * Check that session exists in archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface
     */
    public function exists($id)
    {
        return $this->getArchiveFilePath($id, true)->then(
            function() { return true; }
        );
    }

    /**
     * Remove from archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface which resolved when process complete
     */
    public function remove($id)
    {
        return $this->getArchiveFilePath($id, true)->then(
            function($filePath) {
                $file = \Reaction::$app->fs->file($filePath);
                return $file->remove();
            }
        );
    }

    /**
     * Garbage collector callback
     * @param int $lifeTime Session life time in archive
     * @return ExtendedPromiseInterface which resolved after process complete
     */
    public function gc($lifeTime = 3600)
    {
        \Reaction::info('GC archive');
        return $this->getArchivePath()->then(
            function($pathArchive) {
                $path = \Reaction::$app->fs->dir($pathArchive);
                return $path->ls();
            }
        )->then(
            function($list) use ($lifeTime) {
                $promises = [];
                $expiredTime = time() - $lifeTime;
                foreach ($list as $node) {
                    /** @var FileInterface $node */
                    if (!($node instanceof FileInterface)) continue;
                    $promises[] = $node->time()->then(
                        function($data) use ($expiredTime, $node) {
                            /** @var \DateTime $date */
                            $date = $data['mtime'];
                            if ($date->getTimestamp() < $expiredTime) {
                                return $node->remove()->then(null, function() {
                                    return true;
                                });
                            }
                            return true;
                        },
                        function() { return false; }
                    );
                }
                return !empty($promises) ? \Reaction\Promise\all($promises) : \Reaction\Promise\resolve(true);
            }
        )->then(null, function() {
            return \Reaction\Promise\resolve(false);
        });
    }

    /**
     * Get archive path
     * @return ExtendedPromiseInterface with string
     */
    protected function getArchivePath() {
        if (!isset($this->_archivePath)) {
            $this->_archivePath = \Reaction::$app->getAlias($this->archivePath);
            $path = \Reaction::$app->getAlias($this->archivePath);
            $self = $this;
            $fs = \Reaction::$app->fs;
            $dirAsFile = $fs->file($path);
            $pathPromise = $dirAsFile->exists()->then(
                null,
                function() use ($path) {
                    return FileHelperAsc::createDir($path, 0777, true);
                }
            )->then(
                function() use (&$self, $path) {
                    $self->_archivePath = $path;
                    return $path;
                },
                function() {
                    throw new SessionException("Failed to get session archive path");
                }
            );
            return resolve($pathPromise);
        }
        return resolve($this->_archivePath);
    }

    /**
     * Get session archive file path
     * @param string $id Session id
     * @param bool   $existCheck
     * @return ExtendedPromiseInterface
     */
    protected function getArchiveFilePath($id, $existCheck = false)
    {
        $fileName = $id . '.json';
        return $this->getArchivePath()->then(
            function($dirPath) use ($fileName) {
                return rtrim($dirPath) . DIRECTORY_SEPARATOR . $fileName;
            }
        )->then(
            function($filePath) use ($existCheck) {
                if ($existCheck) {
                    return \Reaction::$app->fs->file($filePath)->exists()->then(
                        function() use ($filePath) {
                            return $filePath;
                        }
                    );
                }
                return resolve($filePath);
            }
        );
    }

    /**
     * Serialize session data
     * @param array $sessionData
     * @return string
     */
    protected function serializeData($sessionData)
    {
        if (is_string($sessionData)) return $sessionData;
        return Json::encode($sessionData);
    }

    /**
     * Unserialize session data
     * @param string $sessionData
     * @return array
     */
    protected function unserializeData($sessionData)
    {
        if (is_array($sessionData)) {
            return $sessionData;
        }
        return Json::decode($sessionData);
    }
}