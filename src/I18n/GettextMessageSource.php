<?php
namespace Reaction\I18n;

use Reaction;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * GettextMessageSource represents a message source that is based on GNU Gettext.
 *
 * Each GettextMessageSource instance represents the message translations
 * for a single domain. And each message category represents a message context
 * in Gettext. Translated messages are stored as either a MO or PO file,
 * depending on the [[useMoFile]] property value.
 *
 * All translations are saved under the [[basePath]] directory.
 *
 * Translations in one language are kept as MO or PO files under an individual
 * subdirectory whose name is the language ID. The file name is specified via
 * [[catalog]] property, which defaults to 'messages'.
 */
class GettextMessageSource extends MessageSource
{
    const MO_FILE_EXT = '.mo';
    const PO_FILE_EXT = '.po';

    /**
     * @var string
     */
    public $basePath = '@app/Messages';
    /**
     * @var string
     */
    public $catalog = 'messages';
    /**
     * @var bool
     */
    public $useMoFile = true;
    /**
     * @var bool
     */
    public $useBigEndian = false;
    /**
     * @var array|null All used categories
     */
    protected $_categories;
    /**
     * @var GettextFile[]
     */
    protected $_gettextFiles = [];


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
     * @return ExtendedPromiseInterface with array the loaded messages. The keys are original messages, and the values are translated messages.
     * @see loadFallbackMessages
     * @see sourceLanguage
     */
    protected function loadMessages($category, $language)
    {
        $messageFile = $this->getMessageFilePath($language);
        return $this->loadMessagesFromFile($messageFile, $category)
            ->otherwise(function() { return null; })
            ->then(function($messages) use ($category, $language, $messageFile) {
                $fallbackLanguage = substr($language, 0, 2);
                $fallbackSourceLanguage = substr($this->sourceLanguage, 0, 2);

                if ($fallbackLanguage !== $language) {
                    return $this->loadFallbackMessages($category, $fallbackLanguage, $messages, $messageFile);
                } elseif ($language === $fallbackSourceLanguage) {
                    return $this->loadFallbackMessages($category, $this->sourceLanguage, $messages, $messageFile);
                } else {
                    if ($messages === null) {
                        Reaction::warning("The message file for category '$category' does not exist: $messageFile");
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
        $fallbackMessageFile = $this->getMessageFilePath($fallbackLanguage);
        return $this->loadMessagesFromFile($fallbackMessageFile, $category)
            ->otherwise(function() { return null; })
            ->then(function($fallbackMessages) use ($category, $fallbackLanguage, $messages, $originalMessageFile, $fallbackMessageFile) {
                if (
                    $messages === null && $fallbackMessages === null
                    && $fallbackLanguage !== $this->sourceLanguage
                    && $fallbackLanguage !== substr($this->sourceLanguage, 0, 2)
                ) {
                    Reaction::warning("The message file for category '$category' does not exist: $originalMessageFile "
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
     * @param string $language the target language
     * @return string path to message file
     */
    protected function getMessageFilePath($language)
    {
        $messageFile = Reaction::$app->getAlias($this->basePath) . '/' . $language . '/' . $this->catalog;
        if ($this->useMoFile) {
            $messageFile .= self::MO_FILE_EXT;
        } else {
            $messageFile .= self::PO_FILE_EXT;
        }

        return $messageFile;
    }

    /**
     * Loads the message translation for the specified language and category or returns null if file doesn't exist.
     *
     * @param string $messageFile path to message file
     * @param string $category the message category
     * @return ExtendedPromiseInterface with array array of messages or null if file not found
     */
    protected function loadMessagesFromFile($messageFile, $category)
    {
        $gettextFile = $this->getGettextFile($messageFile);
        if ($gettextFile === null) {
            return reject(new Reaction\Exceptions\Error("Message file '{$messageFile}' not found"));
        }
        $messages = $gettextFile->load($category);
        if (!is_array($messages)) {
            $messages = [];
        }

        return resolve($messages);
    }

    /**
     * Get gettext file class
     * @param string $messageFile
     * @return GettextFile|null
     */
    protected function getGettextFile($messageFile)
    {
        if (isset($this->_gettextFiles[$messageFile])) {
            return $this->_gettextFiles[$messageFile];
        }
        if (!is_file($messageFile)) {
            return null;
        }
        if ($this->useMoFile) {
            $gettextFile = new GettextMoFile(['filePath' => $messageFile, 'useBigEndian' => $this->useBigEndian]);
        } else {
            $gettextFile = new GettextPoFile(['filePath' => $messageFile]);
        }
        return $this->_gettextFiles[$messageFile] = $gettextFile;
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
        $languages = Reaction::$app->getI18n()->languages;
        $messageFiles = [];
        foreach ($languages as $language) {
            $messageFiles[] = $this->getMessageFilePath($language);
        }

        //Use resolve()->then() to convert PromiseInterface to ExtendedPromiseInterface
        return resolve(true)
            ->then(function() use ($messageFiles) {
                $categories = [];
                foreach ($messageFiles as $messageFile) {
                    $gettext = $this->getGettextFile($messageFile);
                    $categories = Reaction\Helpers\ArrayHelper::merge($categories, $gettext->getCategories());
                }
                $this->_categories = array_unique($categories);
                sort($this->_categories);
                return $this->_categories;
            });
    }
}
