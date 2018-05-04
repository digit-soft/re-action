<?php

namespace Reaction\Helpers\Request;

use Reaction\Exceptions\ErrorException;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidConfigException;

/**
 * Class FileHelper. Proxy to \Reaction\Helpers\FileHelper
 * @package Reaction\Web\RequestComponents
 */
class FileHelper extends RequestHelperProxy
{
    public $helperClass = 'Reaction\Helpers\FileHelper';

    /**
     * Normalizes a file/directory path.
     *
     * The normalization does the following work:
     *
     * - Convert all directory separators into `DIRECTORY_SEPARATOR` (e.g. "\a/b\c" becomes "/a/b/c")
     * - Remove trailing directory separators (e.g. "/a/b/c/" becomes "/a/b/c")
     * - Turn multiple consecutive slashes into a single one (e.g. "/a///b/c" becomes "/a/b/c")
     * - Remove ".." and "." based on their meanings (e.g. "/a/./b/../c" becomes "/a/c")
     *
     * @param string $path the file/directory path to be normalized
     * @param string $ds the directory separator to be used in the normalized result. Defaults to `DIRECTORY_SEPARATOR`.
     * @return string the normalized file/directory path
     * @see \Reaction\Helpers\FileHelper::normalizePath()
     */
    public function normalizePath($path, $ds = DIRECTORY_SEPARATOR)
    {
        return $this->proxy(__FUNCTION__, [$path, $ds]);
    }

    /**
     * Returns the localized version of a specified file.
     *
     * The searching is based on the specified language code. In particular,
     * a file with the same name will be looked for under the subdirectory
     * whose name is the same as the language code. For example, given the file "path/to/view.php"
     * and language code "zh-CN", the localized file will be looked for as
     * "path/to/zh-CN/view.php". If the file is not found, it will try a fallback with just a language code that is
     * "zh" i.e. "path/to/zh/view.php". If it is not found as well the original file will be returned.
     *
     * If the target and the source language codes are the same,
     * the original file will be returned.
     *
     * @param string $file the original file
     * @param string $language the target language that the file should be localized to.
     * If not set, the value of [[\yii\base\Application::language]] will be used.
     * @param string $sourceLanguage the language that the original file is in.
     * If not set, the value of [[\yii\base\Application::sourceLanguage]] will be used.
     * @return string the matching localized file, or the original file if the localized version is not found.
     * If the target and the source language codes are the same, the original file will be returned.
     * @see \Reaction\Helpers\FileHelper::localize()
     */
    public function localize($file, $language = null, $sourceLanguage = null)
    {
        return $this->proxyWithLanguage(__FUNCTION__, [$file, $language, $sourceLanguage], -2);
    }

    /**
     * Determines the MIME type of the specified file.
     * This method will first try to determine the MIME type based on
     * [finfo_open](http://php.net/manual/en/function.finfo-open.php). If the `fileinfo` extension is not installed,
     * it will fall back to [[getMimeTypeByExtension()]] when `$checkExtension` is true.
     * @param string $file the file name.
     * @param string $magicFile name of the optional magic database file (or alias), usually something like `/path/to/magic.mime`.
     * This will be passed as the second parameter to [finfo_open()](http://php.net/manual/en/function.finfo-open.php)
     * when the `fileinfo` extension is installed. If the MIME type is being determined based via [[getMimeTypeByExtension()]]
     * and this is null, it will use the file specified by [[mimeMagicFile]].
     * @param bool $checkExtension whether to use the file extension to determine the MIME type in case
     * `finfo_open()` cannot determine it.
     * @return string the MIME type (e.g. `text/plain`). Null is returned if the MIME type cannot be determined.
     * @throws InvalidConfigException when the `fileinfo` PHP extension is not installed and `$checkExtension` is `false`.
     * @see \Reaction\Helpers\FileHelper::getMimeType()
     */
    public function getMimeType($file, $magicFile = null, $checkExtension = true)
    {
        return $this->proxy(__FUNCTION__, [$file, $magicFile, $checkExtension]);
    }

    /**
     * Determines the MIME type based on the extension name of the specified file.
     * This method will use a local map between extension names and MIME types.
     * @param string $file the file name.
     * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return string|null the MIME type. Null is returned if the MIME type cannot be determined.
     * @see \Reaction\Helpers\FileHelper::getMimeTypeByExtension()
     */
    public function getMimeTypeByExtension($file, $magicFile = null)
    {
        return $this->proxy(__FUNCTION__, [$file, $magicFile]);
    }

    /**
     * Determines the extensions by given MIME type.
     * This method will use a local map between extension names and MIME types.
     * @param string $mimeType file MIME type.
     * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return array the extensions corresponding to the specified MIME type
     * @see \Reaction\Helpers\FileHelper::getExtensionsByMimeType()
     */
    public function getExtensionsByMimeType($mimeType, $magicFile = null)
    {
        return $this->proxy(__FUNCTION__, [$mimeType, $magicFile]);
    }

    /**
     * Loads MIME types from the specified file.
     * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     */
    protected function loadMimeTypes($magicFile)
    {
        return $this->proxy(__FUNCTION__, [$magicFile]);
    }

    /**
     * Loads MIME aliases from the specified file.
     * @param string $aliasesFile the path (or alias) of the file that contains MIME type aliases.
     * If this is not set, the file specified by [[mimeAliasesFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     */
    protected function loadMimeAliases($aliasesFile)
    {
        return $this->proxy(__FUNCTION__, [$aliasesFile]);
    }

    /**
     * Copies a whole directory as another one.
     * The files and sub-directories will also be copied over.
     * @param string $src the source directory
     * @param string $dst the destination directory
     * @param array $options options for directory copy. Valid options are:
     *
     * - dirMode: integer, the permission to be set for newly copied directories. Defaults to 0775.
     * - fileMode:  integer, the permission to be set for newly copied files. Defaults to the current environment setting.
     * - filter: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * true: the directory or file will be copied (the "only" and "except" options will be ignored)
     *   * false: the directory or file will NOT be copied (the "only" and "except" options will be ignored)
     *   * null: the "only" and "except" options will determine whether the directory or file should be copied
     *
     * - only: array, list of patterns that the file paths should match if they want to be copied.
     *   A path matches a pattern if it contains the pattern string at its end.
     *   For example, '.php' matches all file paths ending with '.php'.
     *   Note, the '/' characters in a pattern matches both '/' and '\' in the paths.
     *   If a file path matches a pattern in both "only" and "except", it will NOT be copied.
     * - except: array, list of patterns that the files or directories should match if they want to be excluded from being copied.
     *   A path matches a pattern if it contains the pattern string at its end.
     *   Patterns ending with '/' apply to directory paths only, and patterns not ending with '/'
     *   apply to file paths only. For example, '/a/b' matches all file paths ending with '/a/b';
     *   and '.svn/' matches directory paths ending with '.svn'. Note, the '/' characters in a pattern matches
     *   both '/' and '\' in the paths.
     * - caseSensitive: boolean, whether patterns specified at "only" or "except" should be case sensitive. Defaults to true.
     * - recursive: boolean, whether the files under the subdirectories should also be copied. Defaults to true.
     * - beforeCopy: callback, a PHP callback that is called before copying each sub-directory or file.
     *   If the callback returns false, the copy operation for the sub-directory or file will be cancelled.
     *   The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or
     *   file to be copied from, while `$to` is the copy target.
     * - afterCopy: callback, a PHP callback that is called after each sub-directory or file is successfully copied.
     *   The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or
     *   file copied from, while `$to` is the copy target.
     * - copyEmptyDirectories: boolean, whether to copy empty directories. Set this to false to avoid creating directories
     *   that do not contain files. This affects directories that do not contain files initially as well as directories that
     *   do not contain files at the target destination because files have been filtered via `only` or `except`.
     *   Defaults to true. This option is available since version 2.0.12. Before 2.0.12 empty directories are always copied.
     * @throws InvalidArgumentException if unable to open directory
     * @see \Reaction\Helpers\FileHelper::copyDirectory()
     */
    public function copyDirectory($src, $dst, $options = [])
    {
        $this->proxy(__FUNCTION__, [$src, $dst, $options]);
    }

    /**
     * Removes a directory (and all its content) recursively.
     *
     * @param string $dir the directory to be deleted recursively.
     * @param array $options options for directory remove. Valid options are:
     *
     * - traverseSymlinks: boolean, whether symlinks to the directories should be traversed too.
     *   Defaults to `false`, meaning the content of the symlinked directory would not be deleted.
     *   Only symlink would be removed in that default case.
     *
     * @throws ErrorException in case of failure
     * @see \Reaction\Helpers\FileHelper::removeDirectory()
     */
    public function removeDirectory($dir, $options = [])
    {
        $this->proxy(__FUNCTION__, [$dir, $options]);
    }

    /**
     * Removes a file or symlink in a cross-platform way
     *
     * @param string $path
     * @return bool
     * @see \Reaction\Helpers\FileHelper::unlink()
     */
    public function unlink($path)
    {
        return $this->proxy(__FUNCTION__, [$path]);
    }

    /**
     * Returns the files found under the specified directory and subdirectories.
     * @param string $dir the directory under which the files will be looked for.
     * @param array $options options for file searching. Valid options are:
     *
     * - `filter`: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * `true`: the directory or file will be returned (the `only` and `except` options will be ignored)
     *   * `false`: the directory or file will NOT be returned (the `only` and `except` options will be ignored)
     *   * `null`: the `only` and `except` options will determine whether the directory or file should be returned
     *
     * - `except`: array, list of patterns excluding from the results matching file or directory paths.
     *   Patterns ending with slash ('/') apply to directory paths only, and patterns not ending with '/'
     *   apply to file paths only. For example, '/a/b' matches all file paths ending with '/a/b';
     *   and `.svn/` matches directory paths ending with `.svn`.
     *   If the pattern does not contain a slash (`/`), it is treated as a shell glob pattern
     *   and checked for a match against the pathname relative to `$dir`.
     *   Otherwise, the pattern is treated as a shell glob suitable for consumption by `fnmatch(3)`
     *   with the `FNM_PATHNAME` flag: wildcards in the pattern will not match a `/` in the pathname.
     *   For example, `views/*.php` matches `views/index.php` but not `views/controller/index.php`.
     *   A leading slash matches the beginning of the pathname. For example, `/*.php` matches `index.php` but not `views/start/index.php`.
     *   An optional prefix `!` which negates the pattern; any matching file excluded by a previous pattern will become included again.
     *   If a negated pattern matches, this will override lower precedence patterns sources. Put a backslash (`\`) in front of the first `!`
     *   for patterns that begin with a literal `!`, for example, `\!important!.txt`.
     *   Note, the '/' characters in a pattern matches both '/' and '\' in the paths.
     * - `only`: array, list of patterns that the file paths should match if they are to be returned. Directory paths
     *   are not checked against them. Same pattern matching rules as in the `except` option are used.
     *   If a file path matches a pattern in both `only` and `except`, it will NOT be returned.
     * - `caseSensitive`: boolean, whether patterns specified at `only` or `except` should be case sensitive. Defaults to `true`.
     * - `recursive`: boolean, whether the files under the subdirectories should also be looked for. Defaults to `true`.
     * @return array files found under the directory, in no particular order. Ordering depends on the files system used.
     * @throws InvalidArgumentException if the dir is invalid.
     * @see \Reaction\Helpers\FileHelper::findFiles()
     */
    public function findFiles($dir, $options = [])
    {
        return $this->proxy(__FUNCTION__, [$dir, $options]);
    }

    /**
     * Returns the directories found under the specified directory and subdirectories.
     * @param string $dir the directory under which the files will be looked for.
     * @param array $options options for directory searching. Valid options are:
     *
     * - `filter`: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * `true`: the directory will be returned
     *   * `false`: the directory will NOT be returned
     *
     * - `recursive`: boolean, whether the files under the subdirectories should also be looked for. Defaults to `true`.
     * @return array directories found under the directory, in no particular order. Ordering depends on the files system used.
     * @throws InvalidArgumentException if the dir is invalid.
     * @see \Reaction\Helpers\FileHelper::findDirectories()
     */
    public function findDirectories($dir, $options = [])
    {
        return $this->proxy(__FUNCTION__, [$dir, $options]);
    }

    /**
     * Checks if the given file path satisfies the filtering options.
     * @param string $path the path of the file or directory to be checked
     * @param array $options the filtering options. See [[findFiles()]] for explanations of
     * the supported options.
     * @return bool whether the file or directory satisfies the filtering options.
     * @see \Reaction\Helpers\FileHelper::filterPath()
     */
    public function filterPath($path, $options)
    {
        return $this->proxy(__FUNCTION__, [$path, $options]);
    }

    /**
     * Creates a new directory.
     *
     * This method is similar to the PHP `mkdir()` function except that
     * it uses `chmod()` to set the permission of the created directory
     * in order to avoid the impact of the `umask` setting.
     *
     * @param string $path path of the directory to be created.
     * @param int $mode the permission to be set for the created directory.
     * @param bool $recursive whether to create parent directories if they do not exist.
     * @return bool whether the directory is created successfully
     * @throws \Reaction\Exceptions\Exception if the directory could not be created (i.e. php error due to parallel changes)
     * @see \Reaction\Helpers\FileHelper::createDirectory()
     */
    public function createDirectory($path, $mode = 0775, $recursive = true)
    {
        return $this->proxy(__FUNCTION__, [$path, $mode, $recursive]);
    }
}