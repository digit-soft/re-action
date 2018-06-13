<?php

namespace Reaction\Console\Controllers;

use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\Db\DatabaseInterface;
use Reaction\Db\Query;
use Reaction\Db\TableSchema;
use Reaction\DI\Instance;
use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\Console;
use function Reaction\Promise\allInOrder;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\resolve;
use Reaction\RequestApplicationInterface;

/**
 * Manages application migrations.
 * A migration means a set of persistent changes to the application environment
 * that is shared among different developers. For example, in an application
 * backed by a database, a migration may refer to a set of changes to
 * the database, such as creating a new table, adding a new table column.
 *
 * This command provides support for tracking the migration history, upgrading
 * or downloading with migrations, and creating new migration skeletons.
 *
 * The migration history is stored in a database table named
 * as [[migrationTable]]. The table will be automatically created the first time
 * this command is executed, if it does not exist. You may also manually
 * create it as follows:
 *
 * ```sql
 * CREATE TABLE migration (
 *     version varchar(180) PRIMARY KEY,
 *     apply_time integer
 * )
 * ```
 *
 * Below are some common usages of this command:
 *
 * ```
 * # creates a new migration named 'create_user_table'
 * console migrate/create create_user_table
 *
 * # applies ALL new migrations
 * console migrate
 *
 * # reverts the last applied migration
 * console migrate/down
 * ```
 *
 * You can use namespaced migrations. In order to enable this feature you should configure [[migrationNamespaces]]
 * property for the controller at application configuration:
 *
 * ```php
 * return [
 *     'controllerMap' => [
 *         'migrate' => [
 *             'class' => 'Reaction\Console\Controllers\MigrateController',
 *             'migrationNamespaces' => [
 *                 'App\Migrations',
 *                 'Some\Namespace\Migrations',
 *             ],
 *             //'migrationPath' => null, // allows to disable not namespaced migration completely
 *         ],
 *     ],
 * ];
 * ```
 */
class MigrateController extends BaseMigrateController
{
    /**
     * Maximum length of a migration name.
     */
    const MAX_NAME_LENGTH = 180;

    /**
     * @var string the name of the table for keeping applied migration information.
     */
    public $migrationTable = '{{%migration}}';
    /**
     * {@inheritdoc}
     */
    public $templateFile = '@reaction/Views/migration/migration.php';
    /**
     * @var array a set of template paths for generating migration code automatically.
     *
     * The key is the template type, the value is a path or the alias. Supported types are:
     * - `create_table`: table creating template
     * - `drop_table`: table dropping template
     * - `add_column`: adding new column template
     * - `drop_column`: dropping column template
     * - `create_junction`: create junction template
     */
    public $generatorTemplateFiles = [
        'create_table' => '@reaction/Views/migration/createTableMigration.php',
        'drop_table' => '@reaction/Views/migration/dropTableMigration.php',
        'add_column' => '@reaction/Views/migration/addColumnMigration.php',
        'drop_column' => '@reaction/Views/migration/dropColumnMigration.php',
        'create_junction' => '@reaction/Views/migration/createTableMigration.php',
    ];
    /**
     * @var bool indicates whether the table names generated should consider
     * the `tablePrefix` setting of the DB connection. For example, if the table
     * name is `post` the generator wil return `{{%post}}`.
     */
    public $useTablePrefix = false;
    /**
     * @var array column definition strings used for creating migration code.
     *
     * The format of each definition is `COLUMN_NAME:COLUMN_TYPE:COLUMN_DECORATOR`. Delimiter is `,`.
     * For example, `--fields="name:string(12):notNull:unique"`
     * produces a string column of size 12 which is not null and unique values.
     *
     * Note: primary key is added automatically and is named id by default.
     * If you want to use another name you may specify it explicitly like
     * `--fields="id_key:primaryKey,name:string(12):notNull:unique"`
     */
    public $fields = [];
    /**
     * @var DatabaseInterface|array|string the DB connection object or the application component ID of the DB connection to use
     * when applying migrations. Starting from version 2.0.3, this can also be a configuration array
     * for creating the object.
     */
    public $db = 'db';
    /**
     * @var string the comment for the table being created.
     */
    public $comment = '';


    /**
     * {@inheritdoc}
     */
    public function options($actionId = null)
    {
        return array_merge(
            parent::options($actionId),
            ['migrationTable', 'db'], // global for all actions
            $actionId === 'create'
                ? ['templateFile', 'fields', 'useTablePrefix', 'comment']
                : []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'C' => 'comment',
            'f' => 'fields',
            'p' => 'migrationPath',
            't' => 'migrationTable',
            'F' => 'templateFile',
            'P' => 'useTablePrefix',
            'c' => 'compact',
        ]);
    }

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param string $actionId the action to be executed.
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction($actionId)
    {
        if (parent::beforeAction($actionId)) {
            if (!is_object($this->db)) {
                $this->db = \Reaction::$app->get($this->db);
            }
            return true;
        }

        return false;
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return ExtendedPromiseInterface with \Reaction\Db\Migration the migration instance
     */
    protected function createMigration($class)
    {
        $this->includeMigrationFile($class);

        $migration = \Reaction::create([
            'class' => $class,
            'db' => $this->db,
            'compact' => $this->compact,
        ]);

        if ($migration instanceof ComponentInitBlockingInterface) {
            return $migration->initComponent()
                ->then(function() use ($migration) {
                    return $migration;
                });
        }

        return resolve($migration);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->getSchema()->getTableSchema($this->migrationTable, true) === null) {
            $promise = $this->createMigrationHistoryTable();
        } else {
            $promise = resolve(true);
        }
        return $promise->then(function() use ($limit) {
            $query = (new Query())
                ->select(['version', 'apply_time'])
                ->from($this->migrationTable)
                ->orderBy(['apply_time' => SORT_DESC, 'version' => SORT_DESC]);

            if (empty($this->migrationNamespaces)) {
                $query->limit($limit);
            }
            return $query->all($this->db);
        })->then(function($rows) use ($limit) {
            if (empty($this->migrationNamespaces)) {
                $history = ArrayHelper::map($rows, 'version', 'apply_time');
                unset($history[self::BASE_MIGRATION]);
                return $history;
            }

            $history = [];
            foreach ($rows as $key => $row) {
                if ($row['version'] === self::BASE_MIGRATION) {
                    continue;
                }
                if (preg_match('/m?(\d{6}_?\d{6})(\D.*)?$/is', $row['version'], $matches)) {
                    $time = str_replace('_', '', $matches[1]);
                    $row['canonicalVersion'] = $time;
                } else {
                    $row['canonicalVersion'] = $row['version'];
                }
                $row['apply_time'] = (int)$row['apply_time'];
                $history[] = $row;
            }

            usort($history, function($a, $b) {
                if ($a['apply_time'] === $b['apply_time']) {
                    if (($compareResult = strcasecmp($b['canonicalVersion'], $a['canonicalVersion'])) !== 0) {
                        return $compareResult;
                    }

                    return strcasecmp($b['version'], $a['version']);
                }

                return ($a['apply_time'] > $b['apply_time']) ? -1 : +1;
            });

            $history = array_slice($history, 0, $limit);

            $history = ArrayHelper::map($history, 'version', 'apply_time');

            return $history;
        });
    }

    /**
     * Creates the migration history table.
     * @return ExtendedPromiseInterface
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->getSchema()->getRawTableName($this->migrationTable);
        $this->stdout("Creating migration history table \"$tableName\"...", Console::FG_YELLOW);
        $promises = [];
        $promises[] = $this->db->createCommand()->createTable($this->migrationTable, [
            'version' => 'varchar(' . static::MAX_NAME_LENGTH . ') NOT NULL PRIMARY KEY',
            'apply_time' => 'integer',
        ])->execute();
        $promises[] = $this->db->createCommand()->insert($this->migrationTable, [
            'version' => self::BASE_MIGRATION,
            'apply_time' => time(),
        ])->execute();
        return allInOrder($promises)->then(function() {
            $this->stdout("Done.\n", Console::FG_GREEN);
            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function addMigrationHistory($version)
    {
        $command = $this->db->createCommand();
        return $command->insert($this->migrationTable, [
            'version' => $version,
            'apply_time' => time(),
        ])->execute();
    }

    /**
     * {@inheritdoc}
     */
    protected function truncateDatabase()
    {
        $db = $this->db;
        return $db->getSchema()->getTableSchemas()->then(
            function($schemas) use (&$db) {
                /** @var TableSchema[] $schemas */
                $promises = [];
                // First drop all foreign keys,
                foreach ($schemas as $schema) {
                    if ($schema->foreignKeys) {
                        foreach ($schema->foreignKeys as $name => $foreignKey) {
                            $promises[] = $db->createCommand()->dropForeignKey($name, $schema->name)->execute(true)
                                ->thenLazy(function() use ($name) {
                                    $this->stdout("Foreign key $name dropped.\n");
                                    return true;
                                });
                        }
                    }
                }
                // Then drop the tables:
                foreach ($schemas as $schema) {
                    $promises[] = $db->createCommand()->dropTable($schema->name)->execute(true)
                        ->thenLazy(function() use ($schema) {
                            $this->stdout("Table {$schema->name} dropped.\n");
                            return true;
                        });
                }
                return allInOrder($promises);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function removeMigrationHistory($version)
    {
        $command = $this->db->createCommand();
        return $command->delete($this->migrationTable, [
            'version' => $version,
        ])->execute();
    }

    private $_migrationNameLimit;

    /**
     * {@inheritdoc}
     */
    protected function getMigrationNameLimit()
    {
        if ($this->_migrationNameLimit !== null) {
            return $this->_migrationNameLimit;
        }
        $tableSchema = $this->db->getSchema() ? $this->db->getSchema()->getTableSchema($this->migrationTable, true) : null;
        if ($tableSchema !== null) {
            return $this->_migrationNameLimit = $tableSchema->columns['version']->size;
        }

        return static::MAX_NAME_LENGTH;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateMigrationSourceCode($params, RequestApplicationInterface $app)
    {
        $parsedFields = $this->parseFields();
        $fields = $parsedFields['fields'];
        $foreignKeys = $parsedFields['foreignKeys'];

        $name = $params['name'];

        $templateFile = $this->templateFile;
        $table = null;
        if (preg_match('/^create_junction(?:_table_for_|_for_|_)(.+)_and_(.+)_tables?$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['create_junction'];
            $firstTable = $matches[1];
            $secondTable = $matches[2];

            $fields = array_merge(
                [
                    [
                        'property' => $firstTable . '_id',
                        'decorators' => 'integer()',
                    ],
                    [
                        'property' => $secondTable . '_id',
                        'decorators' => 'integer()',
                    ],
                ],
                $fields,
                [
                    [
                        'property' => 'PRIMARY KEY(' .
                            $firstTable . '_id, ' .
                            $secondTable . '_id)',
                    ],
                ]
            );

            $foreignKeys[$firstTable . '_id']['table'] = $firstTable;
            $foreignKeys[$secondTable . '_id']['table'] = $secondTable;
            $foreignKeys[$firstTable . '_id']['column'] = null;
            $foreignKeys[$secondTable . '_id']['column'] = null;
            $table = $firstTable . '_' . $secondTable;
        } elseif (preg_match('/^add_(.+)_columns?_to_(.+)_table$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['add_column'];
            $table = $matches[2];
        } elseif (preg_match('/^drop_(.+)_columns?_from_(.+)_table$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['drop_column'];
            $table = $matches[2];
        } elseif (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            $this->addDefaultPrimaryKey($fields);
            $templateFile = $this->generatorTemplateFiles['create_table'];
            $table = $matches[1];
        } elseif (preg_match('/^drop_(.+)_table$/', $name, $matches)) {
            $this->addDefaultPrimaryKey($fields);
            $templateFile = $this->generatorTemplateFiles['drop_table'];
            $table = $matches[1];
        }

        foreach ($foreignKeys as $column => $foreignKey) {
            $relatedColumn = $foreignKey['column'];
            $relatedTable = $foreignKey['table'];
            // If related column name is not specified,
            // we're trying to get it from table schema
            // @see https://github.com/yiisoft/yii2/issues/12748
            if ($relatedColumn === null) {
                $relatedColumn = 'id';
                try {
                    $this->db = Instance::ensure($this->db, DatabaseInterface::class);
                    $relatedTableSchema = $this->db->getTableSchema($relatedTable);
                    if ($relatedTableSchema !== null) {
                        $primaryKeyCount = count($relatedTableSchema->primaryKey);
                        if ($primaryKeyCount === 1) {
                            $relatedColumn = $relatedTableSchema->primaryKey[0];
                        } elseif ($primaryKeyCount > 1) {
                            $this->stdout("Related table for field \"{$column}\" exists, but primary key is composite. Default name \"id\" will be used for related field\n", Console::FG_YELLOW);
                        } elseif ($primaryKeyCount === 0) {
                            $this->stdout("Related table for field \"{$column}\" exists, but does not have a primary key. Default name \"id\" will be used for related field.\n", Console::FG_YELLOW);
                        }
                    }
                } catch (\ReflectionException $e) {
                    $this->stdout("Cannot initialize database component to try reading referenced table schema for field \"{$column}\". Default name \"id\" will be used for related field.\n", Console::FG_YELLOW);
                }
            }
            $foreignKeys[$column] = [
                'idx' => $this->generateTableName("idx-$table-$column"),
                'fk' => $this->generateTableName("fk-$table-$column"),
                'relatedTable' => $this->generateTableName($relatedTable),
                'relatedColumn' => $relatedColumn,
            ];
        }

        return $this->renderFile($app, \Reaction::$app->getAlias($templateFile), array_merge($params, [
            'table' => $this->generateTableName($table),
            'fields' => $fields,
            'foreignKeys' => $foreignKeys,
            'tableComment' => $this->comment,
        ]));
    }

    /**
     * If `useTablePrefix` equals true, then the table name will contain the
     * prefix format.
     *
     * @param string $tableName the table name to generate.
     * @return string
     */
    protected function generateTableName($tableName)
    {
        if (!$this->useTablePrefix) {
            return $tableName;
        }

        return '{{%' . $tableName . '}}';
    }

    /**
     * Parse the command line migration fields.
     * @return array parse result with following fields:
     *
     * - fields: array, parsed fields
     * - foreignKeys: array, detected foreign keys
     */
    protected function parseFields()
    {
        $fields = [];
        $foreignKeys = [];

        foreach ($this->fields as $index => $field) {
            $chunks = preg_split('/\s?:\s?/', $field, null);
            $property = array_shift($chunks);

            foreach ($chunks as $i => &$chunk) {
                if (strncmp($chunk, 'foreignKey', 10) === 0) {
                    preg_match('/foreignKey\((\w*)\s?(\w*)\)/', $chunk, $matches);
                    $foreignKeys[$property] = [
                        'table' => isset($matches[1])
                            ? $matches[1]
                            : preg_replace('/_id$/', '', $property),
                        'column' => !empty($matches[2])
                            ? $matches[2]
                            : null,
                    ];

                    unset($chunks[$i]);
                    continue;
                }

                if (!preg_match('/^(.+?)\(([^(]+)\)$/', $chunk)) {
                    $chunk .= '()';
                }
            }
            $fields[] = [
                'property' => $property,
                'decorators' => implode('->', $chunks),
            ];
        }

        return [
            'fields' => $fields,
            'foreignKeys' => $foreignKeys,
        ];
    }

    /**
     * Adds default primary key to fields list if there's no primary key specified.
     * @param array $fields parsed fields
     */
    protected function addDefaultPrimaryKey(&$fields)
    {
        foreach ($fields as $field) {
            if (false !== strripos($field['decorators'], 'primarykey()')) {
                return;
            }
        }
        array_unshift($fields, ['property' => 'id', 'decorators' => 'primaryKey()']);
    }
}