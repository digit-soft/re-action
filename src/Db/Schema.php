<?php

namespace Reaction\Db;

use React\Cache\CacheInterface;
use Reaction\Base\BaseObject;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * Class Schema
 * @package Reaction\Db
 */
class Schema extends BaseObject implements SchemaInterface
{

    /**
     * Schema cache version, to detect incompatibilities in cached values when the
     * data format of the cache changes.
     */
    const SCHEMA_CACHE_VERSION = 1;

    /**
     * @var DatabaseInterface
     */
    public $db;
    /**
     * @var string the default schema name used for the current session.
     */
    public $defaultSchema;
    /**
     * @var string|array column schema class or class config
     */
    public $columnSchemaClass = 'Reaction\Db\ColumnSchema';


    /**
     * @var array list of ALL schema names in the database, except system schemas
     */
    protected $_schemaNames;
    /**
     * @var array list of ALL table names in the database
     */
    protected $_tableNames = [];
    /**
     * @var string|string[] character used to quote schema, table, etc. names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     */
    protected $tableQuoteCharacter = "'";
    /**
     * @var string|string[] character used to quote column names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     */
    protected $columnQuoteCharacter = '"';
    /**
     * @var string server version as a string.
     */
    protected $_serverVersion;


}