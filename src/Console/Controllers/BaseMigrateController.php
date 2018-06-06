<?php

namespace Reaction\Console\Controllers;

use Reaction\Base\BaseObject;
use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\NotSupportedException;
use Reaction\Console\Routes\Controller;
use Reaction\Console\Exception;
use Reaction\Helpers\ClassFinderHelper;
use function Reaction\Promise\allInOrder;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromise;
use Reaction\Promise\LazyPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;
use function Reaction\Promise\resolveLazy;
use Reaction\RequestApplicationInterface;
use Reaction\Db\MigrationInterface;
use Reaction\Helpers\Console;
use Reaction\Helpers\FileHelper;

/**
 * BaseMigrateController is the base class for migrate controllers.
 */
abstract class BaseMigrateController extends Controller
{
    /**
     * The name of the dummy migration that marks the beginning of the whole migration history.
     */
    const BASE_MIGRATION = 'm000000_000000_base';

    /**
     * @var string the default command action.
     */
    public $defaultAction = 'up';
    /**
     * @var string|array the directory containing the migration classes. This can be either
     * a [path alias](guide:concept-aliases) or a directory path.
     *
     * Migration classes located at this path should be declared without a namespace.
     * Use [[migrationNamespaces]] property in case you are using namespaced migrations.
     *
     * If you have set up [[migrationNamespaces]], you may set this field to `null` in order
     * to disable usage of migrations that are not namespaced.
     *
     * You may also specify an array of migration paths that should be searched for
     * migrations to load. This is mainly useful to support old extensions that provide migrations
     * without namespace and to adopt the new feature of namespaced migrations while keeping existing migrations.
     *
     * In general, to load migrations from different locations, [[migrationNamespaces]] is the preferable solution
     * as the migration name contains the origin of the migration in the history, which is not the case when
     * using multiple migration paths.
     *
     * @see $migrationNamespaces
     */
    public $migrationPath = ['@app/Console/Migrations'];
    /**
     * @var array list of namespaces containing the migration classes.
     *
     * Migration namespaces should be resolvable as a [path alias](guide:concept-aliases) if prefixed with `@`, e.g. if you specify
     * the namespace `app\migrations`, the code `Reaction::getAlias('@app/migrations')` should be able to return
     * the file path to the directory this namespace refers to.
     * This corresponds with the [autoloading conventions](guide:concept-autoloading) of framework.
     *
     * For example:
     *
     * ```php
     * [
     *     'App\Migrations',
     *     'Some\Namespace\Migrations',
     * ]
     * ```
     *
     * @see $migrationPath
     */
    public $migrationNamespaces = [];
    /**
     * @var string the template file for generating new migrations.
     * This can be either a [path alias](guide:concept-aliases) (e.g. "@app/migrations/template.php")
     * or a file path.
     */
    public $templateFile;
    /**
     * @var bool indicates whether the console output should be compacted.
     * If this is set to true, the individual commands ran within the migration will not be output to the console.
     * Default is false, in other words the output is fully verbose by default.
     */
    public $compact = false;


    /**
     * {@inheritdoc}
     */
    public function options($actionId = null)
    {
        return array_merge(
            parent::options($actionId),
            ['migrationPath', 'migrationNamespaces', 'compact'], // global for all actions
            $actionId === 'create' ? ['templateFile'] : [] // action create
        );
    }

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param string $actionId the action to be executed.
     * @throws InvalidConfigException if directory specified in migrationPath doesn't exist and action isn't "create".
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction($actionId)
    {
        if (parent::beforeAction($actionId)) {
            if (empty($this->migrationNamespaces) && empty($this->migrationPath)) {
                throw new InvalidConfigException('At least one of `migrationPath` or `migrationNamespaces` should be specified.');
            }

            foreach ($this->migrationNamespaces as $key => $value) {
                $this->migrationNamespaces[$key] = trim($value, '\\');
            }

            if (is_array($this->migrationPath)) {
                foreach ($this->migrationPath as $i => $path) {
                    $this->migrationPath[$i] = \Reaction::$app->getAlias($path);
                }
            } elseif ($this->migrationPath !== null) {
                $path = \Reaction::$app->getAlias($this->migrationPath);
                if (!is_dir($path)) {
                    if ($actionId !== 'create') {
                        throw new InvalidConfigException("Migration failed. Directory specified in migrationPath doesn't exist: {$this->migrationPath}");
                    }
                    FileHelper::createDirectory($path);
                }
                $this->migrationPath = $path;
            }

            $version = \Reaction::getVersion();
            $this->stdout("Reaction Migration Tool (based on Reaction v{$version})\n\n");

            return true;
        }

        return false;
    }

    /**
     * Upgrades the application by applying new migrations.
     *
     * For example,
     *
     * ```
     * console migrate     # apply all new migrations
     * console migrate 3   # apply the first 3 new migrations
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param int                         $limit the number of new migrations to be applied. If 0, it means
     * applying all available new migrations.
     *
     * @return ExtendedPromiseInterface
     */
    public function actionUp(RequestApplicationInterface $app, $limit = 0)
    {
        return $this->getNewMigrations()->then(
            function($migrations) use ($limit) {
                if (empty($migrations)) {
                    $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);

                    return true;
                }

                $total = count($migrations);
                $limit = (int) $limit;
                if ($limit > 0) {
                    $migrations = array_slice($migrations, 0, $limit);
                }

                $n = count($migrations);
                if ($n === $total) {
                    $this->stdout("Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
                } else {
                    $this->stdout("Total $n out of $total new " . ($total === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
                }

                foreach ($migrations as $migration) {
                    $nameLimit = $this->getMigrationNameLimit();
                    if ($nameLimit !== null && strlen($migration) > $nameLimit) {
                        $message = "The migration name '$migration' is too long. Its not possible to apply this migration.";
                        $this->stdout("\n$message\n", Console::FG_RED);
                        throw new Exception($message);
                    }
                    $this->stdout("\t$migration\n");
                }
                $this->stdout("\n");

                $applied = 0;
                return $this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')->then(
                    function() use ($migrations, $n, &$applied) {
                        $promises = [];
                        foreach ($migrations as $migration) {
                            $promises[] = $this->migrateUp($migration)->thenLazy(
                                function() use (&$applied) {
                                    $applied++;
                                    return true;
                                },
                                function($error = null) use ($applied, $n) {
                                    $this->stdout("\n$applied from $n " . ($applied === 1 ? 'migration was' : 'migrations were') . " applied.\n", Console::FG_RED);
                                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                                    $prevException = $error instanceof \Throwable ? $error : null;
                                    $exception = new Exception("Migration failed. The rest of the migrations are canceled", 0, $prevException);
                                    return reject($exception);
                                }
                            );
                        }

                        return allInOrder($promises)->then(
                            function() use ($n) {
                                $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " applied.\n", Console::FG_GREEN);
                                $this->stdout("\nMigrated up successfully.\n", Console::FG_GREEN);
                                return true;
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * Downgrades the application by reverting old migrations.
     *
     * For example,
     *
     * ```
     * console migrate/down     # revert the last migration
     * console migrate/down 3   # revert the last 3 migrations
     * console migrate/down all # revert all migrations
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param int|string                  $limit the number of migrations to be reverted. Defaults to 1,
     * meaning the last applied migration will be reverted. When value is "all", all migrations will be reverted.
     * @return ExtendedPromiseInterface
     * @throws Exception if the number of the steps specified is less than 1.
     */
    public function actionDown(RequestApplicationInterface $app, $limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The step argument must be greater than 0.');
            }
        }

        return $this->getMigrationHistory($limit)->then(
            function($migrations) {
                if (empty($migrations)) {
                    $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);

                    return true;
                }
                $migrations = array_keys($migrations);
                $n = count($migrations);
                $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be reverted:\n", Console::FG_YELLOW);
                foreach ($migrations as $migration) {
                    $this->stdout("\t$migration\n");
                }
                $this->stdout("\n");

                $reverted = 0;

                return $this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')->then(
                    function() use ($migrations, $n, &$reverted) {
                        $promises = [];
                        foreach ($migrations as $migration) {
                            $promises[] = $this->migrateDown($migration)->thenLazy(
                                function() use (&$reverted) {
                                    $reverted++;
                                    return true;
                                },
                                function($error = null) use ($reverted, $n) {
                                    $this->stdout("\n$reverted from $n " . ($reverted === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_RED);
                                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                                    return reject($error);
                                }
                            );
                        }
                        return allInOrder($promises)->then(
                            function() use ($n) {
                                $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_GREEN);
                                $this->stdout("\nMigrated down successfully.\n", Console::FG_GREEN);
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * console migrate/redo     # redo the last applied migration
     * console migrate/redo 3   # redo the last 3 applied migrations
     * console migrate/redo all # redo all migrations
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param int|string                  $limit the number of migrations to be redone. Defaults to 1,
     * meaning the last applied migration will be redone. When equals "all", all migrations will be redone.
     * @return ExtendedPromiseInterface
     * @throws Exception if the number of the steps specified is less than 1.
     */
    public function actionRedo(RequestApplicationInterface $app, $limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The step argument must be greater than 0.');
            }
        }

        return $this->getMigrationHistory($limit)->then(
            function($migrations) {
                if (empty($migrations)) {
                    $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);

                    return true;
                }
                $migrations = array_keys($migrations);

                $n = count($migrations);
                $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n", Console::FG_YELLOW);
                foreach ($migrations as $migration) {
                    $this->stdout("\t$migration\n");
                }
                $this->stdout("\n");

                return $this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')
                    ->then(
                        function() use ($migrations, $n) {
                            $promises = [];
                            foreach ($migrations as $migration) {
                                $promises[] = $this->migrateDown($migration)->thenLazy(
                                    null,
                                    function($error) {
                                        $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                                        return reject($error);
                                    }
                                );
                            }
                            foreach (array_reverse($migrations) as $migration) {
                                $promises[] = $this->migrateUp($migration)->thenLazy(
                                    null,
                                    function($error) {
                                        $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                                        return reject($error);
                                    }
                                );
                            }
                            return allInOrder($promises)->then(function() use ($n) {
                                $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " redone.\n", Console::FG_GREEN);
                                $this->stdout("\nMigration redone successfully.\n", Console::FG_GREEN);
                            });
                        }
                    );
            }
        );
    }

    /**
     * Upgrades or downgrades till the specified version.
     *
     * Can also downgrade versions to the certain apply time in the past by providing
     * a UNIX timestamp or a string parseable by the strtotime() function. This means
     * that all the versions applied after the specified certain time would be reverted.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * console migrate/to 101129_185401                          # using timestamp
     * console migrate/to m101129_185401_create_user_table       # using full name
     * console migrate/to 1392853618                             # using UNIX timestamp
     * console migrate/to "2014-02-15 13:00:50"                  # using strtotime() parseable string
     * console migrate/to App\Migrations\M101129185401CreateUser # using full namespace name
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param string                      $version either the version name or the certain time value in the past
     * that the application should be migrated to. This can be either the timestamp,
     * the full name of the migration, the UNIX timestamp, or the parseable datetime
     * string.
     * @return ExtendedPromiseInterface
     * @throws Exception if the version argument is invalid.
     */
    public function actionTo(RequestApplicationInterface $app, $version)
    {
        if (($namespaceVersion = $this->extractNamespaceMigrationVersion($version)) !== false) {
            return $this->migrateToVersion($app, $namespaceVersion);
        } elseif (($migrationName = $this->extractMigrationVersion($version)) !== false) {
            return $this->migrateToVersion($app, $migrationName);
        } elseif ((string) (int) $version == $version) {
            return $this->migrateToTime($app, $version);
        } elseif (($time = strtotime($version)) !== false) {
            return $this->migrateToTime($app, $time);
        } else {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401),\n the full name of a migration (e.g. m101129_185401_create_user_table),\n the full namespaced name of a migration (e.g. app\\migrations\\M101129185401CreateUserTable),\n a UNIX timestamp (e.g. 1392853000), or a datetime string parseable\nby the strtotime() function (e.g. 2014-02-15 13:00:50).");
        }
    }

    /**
     * Modifies the migration history to the specified version.
     *
     * No actual migration will be performed.
     *
     * ```
     * console migrate/mark 101129_185401                        # using timestamp
     * console migrate/mark m101129_185401_create_user_table     # using full name
     * console migrate/mark app\migrations\M101129185401CreateUser # using full namespace name
     * console migrate/mark m000000_000000_base # reset the complete migration history
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param string                      $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * You may specify the name `m000000_000000_base` to set the migration history to a
     * state where no migration has been applied.
     * @return ExtendedPromiseInterface
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function actionMark(RequestApplicationInterface $app, $version)
    {
        $originalVersion = $version;
        if (($namespaceVersion = $this->extractNamespaceMigrationVersion($version)) !== false) {
            $version = $namespaceVersion;
        } elseif (($migrationName = $this->extractMigrationVersion($version)) !== false) {
            $version = $migrationName;
        } elseif ($version !== static::BASE_MIGRATION) {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table)\nor the full name of a namespaced migration (e.g. app\\migrations\\M101129185401CreateUserTable).");
        }

        // try mark up
        return $this->getNewMigrations()->then(
            function($migrations = []) use ($version, $originalVersion) {
                foreach ($migrations as $i => $migration) {
                    if (strpos($migration, $version) === 0) {
                        if ($this->confirm("Set migration history at $originalVersion?")) {
                            for ($j = 0; $j <= $i; ++$j) {
                                $this->addMigrationHistory($migrations[$j]);
                            }
                            $this->stdout("The migration history is set at $originalVersion.\nNo actual migration was performed.\n", Console::FG_GREEN);
                        }

                        return true;
                    }
                }
                return false;
            }
        )->then(
            function($prevResult = false) use ($version, $originalVersion) {
                if ($prevResult) {
                    return true;
                }
                // try mark down
                return $this->getMigrationHistory(null)->then(
                    function($migrations = []) use ($version, $originalVersion) {
                        $migrations = array_keys($migrations);
                        $migrations[] = static::BASE_MIGRATION;
                        foreach ($migrations as $i => $migration) {
                            if (strpos($migration, $version) === 0) {
                                if ($i === 0) {
                                    $this->stdout("Already at '$originalVersion'. Nothing needs to be done.\n", Console::FG_YELLOW);
                                    return resolve(true);
                                } else {
                                    return $this->confirm("Set migration history at $originalVersion?")->then(
                                        function() use ($migrations, $i, $originalVersion) {
                                            for ($j = 0; $j < $i; ++$j) {
                                                $this->removeMigrationHistory($migrations[$j]);
                                            }
                                            $this->stdout("The migration history is set at $originalVersion.\nNo actual migration was performed.\n", Console::FG_GREEN);
                                        }
                                    );
                                }
                            }
                        }
                        return reject(false);
                    }
                );
            }
        )->then(null, function($error = null) use ($originalVersion) {
            $prevException = $error instanceof \Throwable ? $error : null;
            $exception = new Exception("Unable to find the version '$originalVersion'.", 0, $prevException);
            throw $exception;
        });
    }

    /**
     * Truncates the whole database and starts the migration from the beginning.
     *
     * ```
     * console migrate/fresh
     * ```
     *
     * @param RequestApplicationInterface $app
     * @return ExtendedPromiseInterface
     */
    public function actionFresh(RequestApplicationInterface $app)
    {
        if (\Reaction::isProd()) {
            $this->stdout("App env is set to 'production'.\nRefreshing migrations is not possible on production systems.\n");
            return resolve(true);
        }

        $confirmMessage = "Are you sure you want to reset the database and start the migration from the beginning?\nAll data will be lost irreversibly!";
        return $this->confirm($confirmMessage)->then(
            function() {
                return $this->truncateDatabase();
            },
            function() {
                $this->stdout('Action was cancelled by user. Nothing has been performed.');
                return resolve(true);
            }
        )->then(
            function() use ($app) {
                return $this->actionUp($app);
            }
        );
    }

    /**
     * Displays the migration history.
     *
     * This command will show the list of migrations that have been applied
     * so far. For example,
     *
     * ```
     * console migrate/history     # showing the last 10 migrations
     * console migrate/history 5   # showing the last 5 migrations
     * console migrate/history all # showing the whole history
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param int|string                  $limit the maximum number of migrations to be displayed.
     * If it is "all", the whole migration history will be displayed.
     * @return ExtendedPromiseInterface
     * @throws Exception if invalid limit value passed
     */
    public function actionHistory(RequestApplicationInterface $app, $limit = 10)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The limit must be greater than 0.');
            }
        }

        return $this->getMigrationHistory($limit)->then(
            function($migrations) use ($limit) {
                if (empty($migrations)) {
                    $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
                } else {
                    $n = count($migrations);
                    if ($limit > 0) {
                        $this->stdout("Showing the last $n applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
                    } else {
                        $this->stdout("Total $n " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n", Console::FG_YELLOW);
                    }
                    foreach ($migrations as $version => $time) {
                        $this->stdout("\t(" . date('Y-m-d H:i:s', $time) . ') ' . $version . "\n");
                    }
                }
                return true;
            }
        );
    }

    /**
     * Displays the un-applied new migrations.
     *
     * This command will show the new migrations that have not been applied.
     * For example,
     *
     * ```
     * console migrate/new     # showing the first 10 new migrations
     * console migrate/new 5   # showing the first 5 new migrations
     * console migrate/new all # showing all new migrations
     * ```
     *
     * @param RequestApplicationInterface $app
     * @param int|string                  $limit the maximum number of new migrations to be displayed.
     * If it is `all`, all available new migrations will be displayed.
     * @return ExtendedPromiseInterface
     * @throws Exception if invalid limit value passed
     */
    public function actionNew(RequestApplicationInterface $app, $limit = 10)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The limit must be greater than 0.');
            }
        }

        return $this->getNewMigrations()->then(
            function($migrations) use ($limit) {
                if (empty($migrations)) {
                    $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
                } else {
                    $n = count($migrations);
                    if ($limit && $n > $limit) {
                        $migrations = array_slice($migrations, 0, $limit);
                        $this->stdout("Showing $limit out of $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
                    } else {
                        $this->stdout("Found $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
                    }

                    foreach ($migrations as $migration) {
                        $this->stdout("\t" . $migration . "\n");
                    }
                }
                return true;
            }
        );
    }

    /**
     * Creates a new migration.
     *
     * This command creates a new migration using the available migration template.
     * After using this command, developers should modify the created migration
     * skeleton by filling up the actual migration logic.
     *
     * ```
     * console migrate/create create_user_table
     * ```
     *
     * In order to generate a namespaced migration, you should specify a namespace before the migration's name.
     * Note that backslash (`\`) is usually considered a special character in the shell, so you need to escape it
     * properly to avoid shell errors or incorrect behavior.
     * For example:
     *
     * ```
     * console migrate/create 'app\\migrations\\createUserTable'
     * ```
     *
     * In case [[migrationPath]] is not set and no namespace is provided, the first entry of [[migrationNamespaces]] will be used.
     *
     * @param RequestApplicationInterface $app
     * @param string                      $name the name of the new migration. This should only contain
     * letters, digits, underscores and/or backslashes.
     *
     * Note: If the migration name is of a special form, for example create_xxx or
     * drop_xxx, then the generated migration file will contain extra code,
     * in this case for creating/dropping tables.
     *
     * @return ExtendedPromiseInterface
     * @throws Exception if the name argument is invalid.
     * @throws \Reaction\Exceptions\Exception
     */
    public function actionCreate(RequestApplicationInterface $app, $name)
    {
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            throw new Exception('The migration name should contain letters, digits, underscore and/or backslash characters only.');
        }

        list($namespace, $className) = $this->generateClassName($name);
        // Abort if name is too long
        $nameLimit = $this->getMigrationNameLimit();
        if ($nameLimit !== null && strlen($className) > $nameLimit) {
            throw new Exception('The migration name is too long.');
        }

        $migrationPath = $this->findMigrationPath($namespace);

        $file = $migrationPath . DIRECTORY_SEPARATOR . $className . '.php';
        return $this->confirm("Create new migration '$file'?")->then(
            function() use ($app, $name, $className, $namespace, $migrationPath, $file) {
                $content = $this->generateMigrationSourceCode([
                    'name' => $name,
                    'className' => $className,
                    'namespace' => $namespace,
                ], $app);
                FileHelper::createDirectory($migrationPath);
                file_put_contents($file, $content, LOCK_EX);
                $this->stdout("New migration created successfully.\n", Console::FG_GREEN);
                return true;
            }
        );
    }

    /**
     * Checks if given migration version specification matches namespaced migration name.
     * @param string $rawVersion raw version specification received from user input.
     * @return string|false actual migration version, `false` - if not match.
     */
    protected function extractNamespaceMigrationVersion($rawVersion)
    {
        if (preg_match('/^\\\\?([\w_]+\\\\)+m(\d{6}_?\d{6})(\D.*)?$/is', $rawVersion, $matches)) {
            return trim($rawVersion, '\\');
        }

        return false;
    }

    /**
     * Checks if given migration version specification matches migration base name.
     * @param string $rawVersion raw version specification received from user input.
     * @return string|false actual migration version, `false` - if not match.
     */
    protected function extractMigrationVersion($rawVersion)
    {
        if (preg_match('/^m?(\d{6}_?\d{6})(\D.*)?$/is', $rawVersion, $matches)) {
            return 'm' . $matches[1];
        }

        return false;
    }

    /**
     * Generates class base name and namespace from migration name from user input.
     * @param string $name migration name from user input.
     * @return array list of 2 elements: 'namespace' and 'class base name'
     */
    protected function generateClassName($name)
    {
        $namespace = null;
        $name = trim($name, '\\');
        if (strpos($name, '\\') !== false) {
            $namespace = substr($name, 0, strrpos($name, '\\'));
            $name = substr($name, strrpos($name, '\\') + 1);
        } else {
            if ($this->migrationPath === null) {
                $migrationNamespaces = $this->migrationNamespaces;
                $namespace = array_shift($migrationNamespaces);
            }
        }

        if ($namespace === null) {
            $class = 'm' . gmdate('ymd_His') . '_' . $name;
        } else {
            $class = 'M' . gmdate('ymdHis') . ucfirst($name);
        }

        return [$namespace, $class];
    }

    /**
     * Finds the file path for the specified migration namespace.
     * @param string|null $namespace migration namespace.
     * @return string migration file path.
     * @throws Exception on failure.
     */
    protected function findMigrationPath($namespace)
    {
        if (empty($namespace)) {
            return is_array($this->migrationPath) ? reset($this->migrationPath) : $this->migrationPath;
        }

        if (!in_array($namespace, $this->migrationNamespaces, true)) {
            throw new Exception("Namespace '{$namespace}' not found in `migrationNamespaces`");
        }

        return $this->getNamespacePath($namespace);
    }

    /**
     * Returns the file path matching the give namespace.
     * @param string $namespace namespace.
     * @return string file path.
     */
    protected function getNamespacePath($namespace)
    {
        return ClassFinderHelper::getNamespacePath($namespace);
    }

    /**
     * Upgrades with the specified migration class.
     * @param string $class the migration class name
     * @return LazyPromiseInterface whether the migration is successful
     */
    protected function migrateUp($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return resolveLazy(true);
        }

        $this->stdout("*** applying $class\n", Console::FG_YELLOW);
        $start = microtime(true);
        $promise = new LazyPromise(function() use ($class, $start) {
            return $this->createMigration($class)
                ->then(function($migration) {
                    /** @var MigrationInterface $migration */
                    return $migration->up();
                })
                ->then(function() use ($class, $start) {
                    $time = microtime(true) - $start;
                    $this->stdout("*** applied $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
                    return $this->addMigrationHistory($class);
                }, function($error = null) use ($class, $start) {
                    $time = microtime(true) - $start;
                    $this->stdout("*** failed to apply $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
                    $prevException = $error instanceof \Throwable ? $error : null;
                    $exception = new Exception("Failed to apply $class (time: " . sprintf('%.3f', $time) . "s)", 0, $prevException);
                    throw new $exception;
                });
        });
        return $promise;
    }

    /**
     * Downgrades with the specified migration class.
     * @param string $class the migration class name
     * @return LazyPromiseInterface whether the migration is successful
     */
    protected function migrateDown($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return resolveLazy(true);
        }

        $this->stdout("*** reverting $class\n", Console::FG_YELLOW);
        $start = microtime(true);
        $promise = new LazyPromise(function() use (&$migration, $class, $start) {
            return $this->createMigration($class)
                ->then(function($migration) {
                    /** @var MigrationInterface $migration */
                    return $migration->down();
                })->then(function() use ($class, $start) {
                    $time = microtime(true) - $start;
                    $this->stdout("*** reverted $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
                    return $this->removeMigrationHistory($class);
                }, function($error = null) use ($class, $start) {
                    $time = microtime(true) - $start;
                    $this->stdout("*** failed to revert $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
                    $prevException = $error instanceof \Throwable ? $error : null;
                    $exception = new Exception("Failed to revert $class (time: " . sprintf('%.3f', $time) . "s)", 0, $prevException);
                    throw new $exception;
                });
        });
        return $promise;
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return ExtendedPromiseInterface with \Reaction\Db\MigrationInterface the migration instance
     */
    protected function createMigration($class)
    {
        $this->includeMigrationFile($class);

        /** @var MigrationInterface $migration */
        $migration = \Reaction::create($class);
        if ($migration instanceof BaseObject && $migration->canSetProperty('compact')) {
            $migration->compact = $this->compact;
        }
        if ($migration instanceof ComponentInitBlockingInterface) {
            return $migration->initComponent()
                ->then(function() use ($migration) {
                    return $migration;
                });
        }

        return resolve($migration);
    }

    /**
     * Includes the migration file for a given migration class name.
     *
     * This function will do nothing on namespaced migrations, which are loaded by
     * autoloading automatically. It will include the migration file, by searching
     * [[migrationPath]] for classes without namespace.
     * @param string $class the migration class name.
     */
    protected function includeMigrationFile($class)
    {
        $class = trim($class, '\\');
        if (strpos($class, '\\') === false) {
            if (is_array($this->migrationPath)) {
                foreach ($this->migrationPath as $path) {
                    $file = $path . DIRECTORY_SEPARATOR . $class . '.php';
                    if (is_file($file)) {
                        require_once $file;
                        break;
                    }
                }
            } else {
                $file = $this->migrationPath . DIRECTORY_SEPARATOR . $class . '.php';
                require_once $file;
            }
        }
    }

    /**
     * Migrates to the specified apply time in the past.
     * @param RequestApplicationInterface $app
     * @param int                         $time UNIX timestamp value.
     * @return ExtendedPromiseInterface
     */
    protected function migrateToTime(RequestApplicationInterface $app, $time)
    {
        return $this->getMigrationHistory(null)->then(
            function($migrations) use ($app, $time) {
                $migrations = array_values($migrations);
                $count = 0;
                while ($count < count($migrations) && $migrations[$count] > $time) {
                    ++$count;
                }
                if ($count === 0) {
                    $this->stdout("Nothing needs to be done.\n", Console::FG_GREEN);
                } else {
                    return $this->actionDown($app, $count);
                }
                return reject(new Exception("Nothing needs to be done"));
            }
        );
    }

    /**
     * Migrates to the certain version.
     * @param RequestApplicationInterface $app
     * @param string                      $version name in the full format.
     * @return ExtendedPromiseInterface
     * @throws Exception if the provided version cannot be found.
     */
    protected function migrateToVersion(RequestApplicationInterface $app, $version)
    {
        $originalVersion = $version;

        return $this->getNewMigrations()->then(
            function($migrations) use ($app, $version) {
                foreach ($migrations as $i => $migration) {
                    if (strpos($migration, $version) === 0) {
                        return $this->actionUp($app, $i + 1);
                    }
                }
                return reject(null);
            }
        )->then(null, function() use ($app, $version, $originalVersion) {
            return $this->getMigrationHistory(null)
                ->then(function($migrations) use ($app, $version, $originalVersion) {
                    $migrations = array_keys($migrations);
                    foreach ($migrations as $i => $migration) {
                        if (strpos($migration, $version, $originalVersion) === 0) {
                            if ($i === 0) {
                                $this->stdout("Already at '$originalVersion'. Nothing needs to be done.\n", Console::FG_YELLOW);
                            } else {
                                $this->actionDown($app, $i);
                            }

                            return resolve(true);
                        }
                    }
                    throw new Exception("Unable to find the version '$originalVersion'.");
                });
        });
    }

    /**
     * Returns the migrations that are not applied.
     * @return ExtendedPromiseInterface with array list of new migrations
     */
    protected function getNewMigrations()
    {
        return $this->getMigrationHistory(null)->then(function($history) {
            $applied = [];
            foreach ($history as $class => $time) {
                $applied[trim($class, '\\')] = true;
            }
            $migrationPaths = [];
            if (is_array($this->migrationPath)) {
                foreach ($this->migrationPath as $path) {
                    $migrationPaths[] = [$path, ''];
                }
            } elseif (!empty($this->migrationPath)) {
                $migrationPaths[] = [$this->migrationPath, ''];
            }
            foreach ($this->migrationNamespaces as $namespace) {
                $migrationPaths[] = [$this->getNamespacePath($namespace), $namespace];
            }

            $migrations = [];
            foreach ($migrationPaths as $item) {
                list($migrationPath, $namespace) = $item;
                if (!file_exists($migrationPath)) {
                    continue;
                }
                $handle = opendir($migrationPath);
                while (($file = readdir($handle)) !== false) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $path = $migrationPath . DIRECTORY_SEPARATOR . $file;
                    if (preg_match('/^(m(\d{6}_?\d{6})\D.*?)\.php$/is', $file, $matches) && is_file($path)) {
                        $class = $matches[1];
                        if (!empty($namespace)) {
                            $class = $namespace . '\\' . $class;
                        }
                        $time = str_replace('_', '', $matches[2]);
                        if (!isset($applied[$class])) {
                            $migrations[$time . '\\' . $class] = $class;
                        }
                    }
                }
                closedir($handle);
            }
            ksort($migrations);
            \Reaction::warning($migrations);

            return array_values($migrations);
        });
    }

    /**
     * Generates new migration source PHP code.
     * Child class may override this method, adding extra logic or variation to the process.
     * @param array                       $params generation parameters, usually following parameters are present:
     *
     *  - name: string migration base name
     *  - className: string migration class name
     *
     * @param RequestApplicationInterface $app
     * @return string generated PHP code.
     */
    protected function generateMigrationSourceCode($params, RequestApplicationInterface $app)
    {
        return $this->renderFile($app, \Reaction::$app->getAlias($this->templateFile), $params);
    }

    /**
     * Truncates the database.
     * This method should be overwritten in subclasses to implement the task of clearing the database.
     * @throws NotSupportedException if not overridden
     * @return ExtendedPromiseInterface when finished
     */
    protected function truncateDatabase()
    {
        throw new NotSupportedException('This command is not implemented in ' . get_class($this));
    }

    /**
     * Return the maximum name length for a migration.
     *
     * Subclasses may override this method to define a limit.
     * @return int|null the maximum name length for a migration or `null` if no limit applies.
     */
    protected function getMigrationNameLimit()
    {
        return null;
    }

    /**
     * Returns the migration history.
     * @param int $limit the maximum number of records in the history to be returned. `null` for "no limit".
     * @return ExtendedPromiseInterface with array the migration history
     */
    abstract protected function getMigrationHistory($limit);

    /**
     * Adds new migration entry to the history.
     * @param string $version migration version name.
     * @return ExtendedPromiseInterface when finished
     */
    abstract protected function addMigrationHistory($version);

    /**
     * Removes existing migration from the history.
     * @param string $version migration version name.
     * @return ExtendedPromiseInterface when finished
     */
    abstract protected function removeMigrationHistory($version);
}
