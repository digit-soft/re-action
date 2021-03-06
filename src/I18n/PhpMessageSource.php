<?php

namespace Reaction\I18n;

use React\Filesystem\Node\FileInterface;
use Reaction;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * PhpMessageSource represents a message source that stores translated messages in PHP scripts.
 *
 * PhpMessageSource uses PHP arrays to keep message translations.
 *
 * - Each PHP script contains one array which stores the message translations in one particular
 *   language and for a single message category;
 * - Each PHP script is saved as a file named as "[[basePath]]/LanguageID/CategoryName.php";
 * - Within each PHP script, the message translations are returned as an array like the following:
 *
 * ```php
 * return [
 *     'original message 1' => 'translated message 1',
 *     'original message 2' => 'translated message 2',
 * ];
 * ```
 *
 * You may use [[fileMap]] to customize the association between category names and the file names.
 */
class PhpMessageSource extends MessageSource
{
    /**
     * @var string the base path for all translated messages. Defaults to '@app/Messages'.
     */
    public $basePath = '@app/Messages';
    /**
     * @var array mapping between message categories and the corresponding message file paths.
     * The file paths are relative to [[basePath]]. For example,
     *
     * ```php
     * [
     *     'core' => 'core.php',
     *     'ext' => 'extensions.php',
     * ]
     * ```
     */
    public $fileMap;
    /**
     * @var array|null All used categories
     */
    protected $_categories;


    /**
     * Loads the message translation for the specified $language and $category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`. When both are present, the `en-US` messages will be merged
     * over `en`. See [[loadFallbackMessages]] for details.
     * If the $language is less specific than [[sourceLanguage]], the method will try to
     * load the messages for [[sourceLanguage]]. For example: [[sourceLanguage]] is `en-GB`,
     * $language is `en`. The method will load the messages for `en` and merge them over `en-GB`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return ExtendedPromiseInterface with array the loaded messages. The keys are original messages, and the values are the translated messages.
     * @see loadFallbackMessages
     * @see sourceLanguage
     */
    protected function loadMessages($category, $language)
    {
        $messageFile = $this->getMessageFilePath($category, $language);
        $method = __METHOD__;
        return $this->loadMessagesFromFile($messageFile)
            ->otherwise(function() { return null; })
            ->then(function($messages) use ($category, $language, $messageFile, $method) {
                $fallbackLanguage = substr($language, 0, 2);
                $fallbackSourceLanguage = substr($this->sourceLanguage, 0, 2);

                if ($language !== $fallbackLanguage) {
                    return $this->loadFallbackMessages($category, $fallbackLanguage, $messages, $messageFile);
                } elseif ($language === $fallbackSourceLanguage) {
                    return $this->loadFallbackMessages($category, $this->sourceLanguage, $messages, $messageFile);
                } else {
                    if ($messages === null) {
                        Reaction::warning("The message file for category '$category' does not exist: $messageFile in {$method}");
                    }
                    return (array)$messages;
                }
            });
    }

    /**
     * The method is normally called by [[loadMessages]] to load the fallback messages for the language.
     * Method tries to load the $category messages for the $fallbackLanguage and adds them to the $messages array.
     *
     * @param string $category the message category
     * @param string $fallbackLanguage the target fallback language
     * @param array $messages the array of previously loaded translation messages.
     * The keys are original messages, and the values are the translated messages.
     * @param string $originalMessageFile the path to the file with messages. Used to log an error message
     * in case when no translations were found.
     * @return ExtendedPromiseInterface with array the loaded messages. The keys are original messages, and the values are the translated messages.
     */
    protected function loadFallbackMessages($category, $fallbackLanguage, $messages, $originalMessageFile)
    {
        $fallbackMessageFile = $this->getMessageFilePath($category, $fallbackLanguage);
        return $this->loadMessagesFromFile($fallbackMessageFile)
            ->otherwise(function() { return null; })
            ->then(function($fallbackMessages) use ($category, $messages, $fallbackMessageFile, $fallbackLanguage, $originalMessageFile) {
                if (
                    $messages === null && $fallbackMessages === null
                    && $fallbackLanguage !== $this->sourceLanguage
                    && $fallbackLanguage !== substr($this->sourceLanguage, 0, 2)
                ) {
                    Reaction::error("The message file for category '$category' does not exist: $originalMessageFile "
                        . "Fallback file does not exist as well: $fallbackMessageFile");
                } elseif (empty($messages)) {
                    return $fallbackMessages;
                } elseif (!empty($fallbackMessages)) {
                    foreach ($fallbackMessages as $key => $value) {
                        if (!empty($value) && empty($messages[$key])) {
                            $messages[$key] = $fallbackMessages[$key];
                        }
                    }
                }

                return (array)$messages;
            });
    }

    /**
     * Returns message file path for the specified language and category.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return string path to message file
     */
    protected function getMessageFilePath($category, $language)
    {
        $messageFile = Reaction::$app->getAlias($this->basePath) . "/$language/";
        if (isset($this->fileMap[$category])) {
            $messageFile .= $this->fileMap[$category];
        } else {
            $messageFile .= str_replace('\\', '/', $category) . '.php';
        }

        return $messageFile;
    }

    /**
     * Loads the message translation for the specified language and category or returns null if file doesn't exist.
     *
     * @param string $messageFile path to message file
     * @return ExtendedPromiseInterface with array of messages or null if file not found
     */
    protected function loadMessagesFromFile($messageFile)
    {
        if (is_file($messageFile)) {
            $messages = include $messageFile;
            if (!is_array($messages)) {
                $messages = [];
            }

            return resolve($messages);
        }

        return reject(new Reaction\Exceptions\Error("Message file '{$messageFile}' not found"));
    }

    /**
     * Find all categories used in message source
     * @return ExtendedPromiseInterface
     */
    protected function findAllCategories()
    {
        if (isset($this->_categories)) {
            return resolve($this->_categories);
        }
        $categoryMap = is_array($this->fileMap) ? array_flip($this->fileMap) : [];
        $baseDirPath = Reaction::getAlias($this->basePath);
        //Use resolve()->then() to convert PromiseInterface to ExtendedPromiseInterface
        return resolve(true)
            ->then(function() use ($baseDirPath, $categoryMap) {
                $dir = Reaction\Helpers\FileHelperAsc::dir($baseDirPath);
                return $dir->lsRecursive()
                    ->then(function($results) use ($baseDirPath, $categoryMap) {
                        $categories = [];
                        foreach ($results as $result) {
                            if (!is_object($result) || !$result instanceof FileInterface || substr($result->getPath(), -4) !== '.php') {
                                continue;
                            }
                            //Remove base path part
                            $filePath = substr($result->getPath(), mb_strlen($baseDirPath) + 1);
                            //Remove language part
                            if (($langSepPos = strpos($filePath, DIRECTORY_SEPARATOR)) !== false) {
                                $filePath = substr($filePath, $langSepPos + 1);
                            }
                            //Search category in file map
                            if (isset($categoryMap[$filePath])) {
                                $categories[] = $categoryMap[$filePath];
                                continue;
                            } else {
                                //Remove .php extension
                                $filePath = substr($filePath, 0, -4);
                            }
                            //Add possible category with another slashes
                            if (strpos($filePath, DIRECTORY_SEPARATOR) !== false) {
                                $categories[] = str_replace(DIRECTORY_SEPARATOR, '\\', $filePath);
                            }
                            $categories[] = $filePath;
                        }
                        $this->_categories = array_unique($categories);
                        sort($this->_categories);
                        return $this->_categories;
                    });
            });

    }
}
