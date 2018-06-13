<?php

namespace Reaction\I18n;

use React\Promise\PromiseInterface;
use Reaction;
use Reaction\Base\Component;
use Reaction\Base\ComponentAutoloadInterface;
use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\Exceptions\InvalidConfigException;
use function Reaction\Promise\all;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * I18N provides features related with internationalization (I18N) and localization (L10N).
 *
 * I18N is configured as an application component in [[\Reaction\StaticApplicationInterface]] by default.
 * You can access that instance via `Reaction::$app->i18n` || `Reaction::$app->getI18n()`.
 *
 * If you are using DbMessageSource, then add database component as dependency so it will init first,
 * for example:
 *
 * ```
 * ...
 * //Internationalization
 *  'i18n' => [
 *      'class' => 'Reaction\I18n\I18N',
 *      'languages' => ['uk', 'ru'],
 *      'translations' => [
 *          '*' => [
 *              'class' => 'Reaction\I18n\GettextMessageSource',
 *              'basePath' => '@app/Messages',
 *          ],
 *      ],
 *      'dependsOn' => ['db'], //'i18n' component depends on 'db' component
 *  ],
 * ...
 *
 * @property MessageFormatter $messageFormatter The message formatter to be used to format message via ICU
 * message format. Note that the type of this property differs in getter and setter. See
 * [[getMessageFormatter()]] and [[setMessageFormatter()]] for details.
 */
class I18N extends Component implements ComponentAutoloadInterface, ComponentInitBlockingInterface
{
    const DOMAIN_CORE = 'rct';

    /**
     * @var array list of [[MessageSource]] configurations or objects. The array keys are message
     * category patterns, and the array values are the corresponding [[MessageSource]] objects or the configurations
     * for creating the [[MessageSource]] objects.
     *
     * The message category patterns can contain the wildcard `*` at the end to match multiple categories with the same prefix.
     * For example, `app/*` matches both `app/cat1` and `app/cat2`.
     *
     * The `*` category pattern will match all categories that do not match any other category patterns.
     *
     * This property may be modified on the fly by extensions who want to have their own message sources
     * registered under their own namespaces.
     *
     * The category `yii` and `app` are always defined. The former refers to the messages used in the Yii core
     * framework code, while the latter refers to the default message category for custom application code.
     * By default, both of these categories use [[PhpMessageSource]] and the corresponding message files are
     * stored under `@reaction/Messages` and `@app/Messages`, respectively.
     *
     * You may override the configuration of both categories.
     */
    public $translations;
    /**
     * @var string[] List of used languages to preload messages on init
     */
    public $languages = [];
    /**
     * @var bool
     */
    protected $_initialized = false;


    /**
     * Initializes the component by configuring the default message categories.
     */
    public function init()
    {
        parent::init();
        if (empty($this->languages)) {
            $this->languages = [Reaction::$app->language];
        }
        if (!isset($this->translations['rct']) && !isset($this->translations['rct*'])) {
            $this->translations['rct'] = [
                'class' => 'Reaction\I18n\PhpMessageSource',
                'sourceLanguage' => 'en-US',
                'basePath' => '@reaction/Messages',
            ];
        }

        if (!isset($this->translations['app']) && !isset($this->translations['app*']) && !isset($this->translations['*'])) {
            $this->translations['app'] = [
                'class' => 'Reaction\I18n\PhpMessageSource',
                'sourceLanguage' => Reaction::$app->sourceLanguage,
                'basePath' => '@app/Messages',
            ];
        }
    }

    /**
     * Translates a message to the specified language.
     *
     * After translation the message will be formatted using [[MessageFormatter]] if it contains
     * ICU message format and `$params` are not empty.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the translated and formatted message.
     */
    public function translate($category, $message, $params, $language)
    {
        $messageSource = $this->getMessageSource($category);
        $translation = $messageSource->translate($category, $message, $language);
        if ($translation === false) {
            return $this->format($message, $params, $messageSource->sourceLanguage);
        }

        return $this->format($translation, $params, $language);
    }

    /**
     * Formats a message using [[MessageFormatter]].
     *
     * @param string $message the message to be formatted.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the formatted message.
     */
    public function format($message, $params, $language)
    {
        $params = (array)$params;
        if ($params === []) {
            return $message;
        }

        if (preg_match('~{\s*[\w.]+\s*,~u', $message)) {
            $formatter = $this->getMessageFormatter();
            $result = $formatter->format($message, $params, $language);
            if ($result === false) {
                $errorMessage = $formatter->getErrorMessage();
                Reaction::warning("Formatting message for language '$language' failed with error: $errorMessage. The message being formatted was: $message.");

                return $message;
            }

            return $result;
        }

        $p = [];
        foreach ($params as $name => $value) {
            $p['{' . $name . '}'] = $value;
        }

        return strtr($message, $p);
    }

    /**
     * @var string|array|MessageFormatter
     */
    private $_messageFormatter;

    /**
     * Returns the message formatter instance.
     * @return MessageFormatter the message formatter to be used to format message via ICU message format.
     */
    public function getMessageFormatter()
    {
        if ($this->_messageFormatter === null) {
            $this->_messageFormatter = new MessageFormatter();
        } elseif (is_array($this->_messageFormatter) || is_string($this->_messageFormatter)) {
            $this->_messageFormatter = Reaction::create($this->_messageFormatter);
        }

        return $this->_messageFormatter;
    }

    /**
     * @param string|array|MessageFormatter $value the message formatter to be used to format message via ICU message format.
     * Can be given as array or string configuration that will be given to [[Yii::createObject]] to create an instance
     * or a [[MessageFormatter]] instance.
     */
    public function setMessageFormatter($value)
    {
        $this->_messageFormatter = $value;
    }

    /**
     * Returns the message source for the given category.
     * @param string $category the category name.
     * @return MessageSource the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function getMessageSource($category)
    {
        if (isset($this->translations[$category])) {
            $source = $this->translations[$category];
            if ($source instanceof MessageSource) {
                return $source;
            }

            return $this->translations[$category] = Reaction::create($source);
        }
        // try wildcard matching
        foreach ($this->translations as $pattern => $source) {
            if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                if ($source instanceof MessageSource) {
                    return $source;
                }

                return $this->translations[$category] = $this->translations[$pattern] = Reaction::create($source);
            }
        }

        // match '*' in the last
        if (isset($this->translations['*'])) {
            $source = $this->translations['*'];
            if ($source instanceof MessageSource) {
                return $source;
            }

            return $this->translations[$category] = $this->translations['*'] = Reaction::create($source);
        }

        throw new InvalidConfigException("Unable to locate message source for category '$category'.");
    }

    /**
     * Init callback. Called by parent container/service/component on init and must return a fulfilled Promise
     * @return PromiseInterface
     */
    public function initComponent()
    {
        return resolve(true)
            ->then(function() {
                $promises = [];
                foreach ($this->translations as $category => $config) {
                    $source = $this->getMessageSource($category);
                    $promises[] = $source
                        ->preloadMessages($category, $this->languages)
                        ->otherwise(function($error) {
                            echo gettype($error);
                        });
                }
                if (empty($promises)) {
                    return true;
                }
                return all($promises);
            })->then(function() {
                $initMessage = sprintf("%s initialized", __CLASS__);
                if (!Reaction::isConsoleApp()) {
                    Reaction::info($initMessage);
                }
                return $this->_initialized = true;
            })->otherwise(function($error) {
                if ($error instanceof \Throwable) {
                    Reaction::error($error->getMessage());
                }
                return reject($error);
            });
    }

    /**
     * Check that component was initialized earlier
     * @return bool
     */
    public function isInitialized()
    {
        return $this->_initialized;
    }
}
