<?php

namespace Reaction\I18n;

use Reaction;
use Reaction\Cache\ExpiringCacheInterface;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Db\DatabaseInterface;
use Reaction\Db\Expressions\Expression;
use Reaction\Db\Query;
use Reaction\DI\Instance;
use Reaction\Helpers\ArrayHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\resolve;

/**
 * DbMessageSource extends [[MessageSource]] and represents a message source that stores translated
 * messages in database.
 *
 * The database must contain the following two tables: source_message and message.
 *
 * The `source_message` table stores the messages to be translated, and the `message` table stores
 * the translated messages. The name of these two tables can be customized by setting [[sourceMessageTable]]
 * and [[messageTable]], respectively.
 *
 * The database connection is specified by [[db]]. Database schema could be initialized by applying migration:
 *
 * ```
 * console migrate --migrationPath=@reaction/I18n/Migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 */
class DbMessageSource extends MessageSource
{
    /**
     * @var DatabaseInterface|array|string the DB connection object or the application component ID of the DB connection.
     *
     * After the DbMessageSource object is created, if you want to change this property, you should only assign
     * it with a DB connection object.
     *
     * This can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var ExpiringCacheInterface|array|string the cache object or the application component ID of the cache object.
     * The messages data will be cached using this cache object.
     * Note, that to enable caching you have to set [[enableCaching]] to `true`, otherwise setting this property has no effect.
     *
     * After the DbMessageSource object is created, if you want to change this property, you should only assign
     * it with a cache object.
     *
     * This can also be a configuration array for creating the object.
     * @see cachingDuration
     * @see enableCaching
     */
    public $cache = 'cache';
    /**
     * @var string the name of the source message table.
     */
    public $sourceMessageTable = '{{%source_message}}';
    /**
     * @var string the name of the translated message table.
     */
    public $messageTable = '{{%message}}';
    /**
     * @var int the time in seconds that the messages can remain valid in cache.
     * @see enableCaching
     */
    public $cachingDuration = 3600;
    /**
     * @var bool whether to enable caching translated messages
     */
    public $enableCaching = false;
    /**
     * @var array|null All used categories
     */
    protected $_categories;


    /**
     * Initializes the DbMessageSource component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * Configured [[cache]] component would also be initialized.
     * @throws InvalidConfigException if [[db]] is invalid or [[cache]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, DatabaseInterface::class);
        if ($this->enableCaching) {
            $this->cache = Instance::ensure($this->cache, 'Reaction\Cache\ExpiringCacheInterface');
        }
    }

    /**
     * Loads the message translation for the specified language and category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return ExtendedPromiseInterface array the loaded messages. The keys are original messages, and the values
     * are translated messages.
     */
    protected function loadMessages($category, $language)
    {
        if ($this->enableCaching) {
            $cacheKey = [
                __CLASS__,
                $category,
                $language,
            ];
            return $this->cache
                ->get($cacheKey)
                ->otherwise(function() use ($category, $language, $cacheKey) {
                    $messages = [];
                    return $this->loadMessagesFromDb($category, $language)
                        ->then(function($messagesDb) use (&$messages, $cacheKey) {
                            $messages = $messagesDb;
                            return $this->cache->set($cacheKey, $messagesDb, $this->cachingDuration);
                        })->then(function() use (&$messages) {
                            return $messages;
                        });
                });
        }

        return $this->loadMessagesFromDb($category, $language);
    }

    /**
     * Loads the messages from database.
     * You may override this method to customize the message storage in the database.
     * @param string $category the message category.
     * @param string $language the target language.
     * @return ExtendedPromiseInterface with array the messages loaded from database.
     */
    protected function loadMessagesFromDb($category, $language)
    {
        $mainQuery = (new Query())->select(['message' => 't1.message', 'translation' => 't2.translation'])
            ->from(['t1' => $this->sourceMessageTable, 't2' => $this->messageTable])
            ->where([
                't1.id' => new Expression('[[t2.id]]'),
                't1.category' => $category,
                't2.language' => $language,
            ]);

        $fallbackLanguage = substr($language, 0, 2);
        $fallbackSourceLanguage = substr($this->sourceLanguage, 0, 2);

        if ($fallbackLanguage !== $language) {
            $mainQuery->union($this->createFallbackQuery($category, $language, $fallbackLanguage), true);
        } elseif ($language === $fallbackSourceLanguage) {
            $mainQuery->union($this->createFallbackQuery($category, $language, $fallbackSourceLanguage), true);
        }

        return $mainQuery->createCommand($this->db)
            ->queryAll()
            ->then(function($messages) {
                return ArrayHelper::map($messages, 'message', 'translation');
            });
    }

    /**
     * The method builds the [[Query]] object for the fallback language messages search.
     * Normally is called from [[loadMessagesFromDb]].
     *
     * @param string $category the message category
     * @param string $language the originally requested language
     * @param string $fallbackLanguage the target fallback language
     * @return Query
     * @see loadMessagesFromDb
     */
    protected function createFallbackQuery($category, $language, $fallbackLanguage)
    {
        return (new Query())->select(['message' => 't1.message', 'translation' => 't2.translation'])
            ->from(['t1' => $this->sourceMessageTable, 't2' => $this->messageTable])
            ->where([
                't1.id' => new Expression('[[t2.id]]'),
                't1.category' => $category,
                't2.language' => $fallbackLanguage,
            ])->andWhere([
                'NOT IN', 't2.id', (new Query())->select('[[id]]')->from($this->messageTable)->where(['language' => $language]),
            ]);
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
        return (new Query())->select(['category'])
            ->from($this->sourceMessageTable)
            ->orderBy(['category' => SORT_ASC])
            ->groupBy(['category'])
            ->column($this->db)
            ->then(function($categories) {
                return $this->_categories = $categories;
            });
    }
}
