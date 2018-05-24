<?php

namespace Reaction\Db;

use React\Promise\PromiseInterface;
use Reaction\Base\Component;
use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\DI\Instance;
use Reaction\Exceptions\Exception;
use Reaction\Helpers\StringHelper;
use function Reaction\Promise\allInOrder;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Promise\LazyPromise;
use Reaction\Promise\LazyPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;
use function ReturnTypes\returnAlias;

/**
 * Migration is the base class for representing a database migration.
 *
 * Migration is designed to be used together with the "yii migrate" command.
 *
 * Each child class of Migration represents an individual database migration which
 * is identified by the child class name.
 *
 * Within each migration, the [[up()]] method should be overridden to contain the logic
 * for "upgrading" the database; while the [[down()]] method for the "downgrading"
 * logic. The "yii migrate" command manages all available migrations in an application.
 *
 * If the database supports transactions, you may also override [[safeUp()]] and
 * [[safeDown()]] so that if anything wrong happens during the upgrading or downgrading,
 * the whole migration can be reverted in a whole.
 *
 * Note that some DB queries in some DBMS cannot be put into a transaction. For some examples,
 * please refer to [implicit commit](http://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html). If this is the case,
 * you should still implement `up()` and `down()`, instead.
 *
 * Migration provides a set of convenient methods for manipulating database data and schema.
 * For example, the [[insert()]] method can be used to easily insert a row of data into
 * a database table; the [[createTable()]] method can be used to create a database table.
 * Compared with the same methods in [[Command]], these methods will display extra
 * information showing the method parameters and execution time, which may be useful when
 * applying migrations.
 *
 * For more details and usage information on Migration, see the [guide article on Migration](guide:db-migrations).
 */
class Migration extends Component implements MigrationInterface, ComponentInitBlockingInterface
{
    use SchemaBuilderTrait;

    /**
     * @var DatabaseInterface|array|string the DB object or the application component ID of the DB
     * that this migration should work with. This can also be a configuration array for creating the object.
     *
     * Note that when a Migration object is created by the `migrate` command, this property will be overwritten
     * by the command. If you do not want to use the DB provided by the command, you may override
     * the [[init()]] method like the following:
     *
     * ```php
     * public function init()
     * {
     *     $this->db = 'db2';
     *     parent::init();
     * }
     * ```
     */
    public $db = 'db';
    /**
     * @var int max number of characters of the SQL outputted. Useful for reduction of long statements and making
     * console output more compact.
     */
    public $maxSqlOutputLength;
    /**
     * @var bool indicates whether the console output should be compacted.
     * If this is set to true, the individual commands ran within the migration will not be output to the console.
     * Default is false, in other words the output is fully verbose by default.
     */
    public $compact = false;
    /**
     * @var bool Flag whenever component is initialized or not
     */
    protected $_initialized = false;


    /**
     * TODO: make async init
     * Initializes the migration.
     * This method will set [[db]] to be the 'db' application component, if it is `null`.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, DatabaseInterface::className());
    }

    /**
     * Init callback. Called by parent container/service/component on init and must return a fulfilled Promise
     * @return PromiseInterface
     */
    public function initComponent()
    {
        return $this->db->getSchema()->refresh();
    }

    /**
     * Check that component was initialized earlier
     * @return bool
     */
    public function isInitialized()
    {
        return $this->_initialized;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDb()
    {
        return $this->db;
    }

    /**
     * This method contains the logic to be executed when applying this migration.
     * Child classes may override this method to provide actual migration logic.
     * @return ExtendedPromiseInterface
     * Resolved promise mean the migration succeeds and rejected - not.
     */
    public function up()
    {
        $transaction = $this->db->createTransaction();
        return $transaction->begin()->thenLazy(
            function($connection) {
                $promise = $this->safeUp($connection);
                return is_array($promise) ? allInOrder($promise) : $promise;
            }
        )->thenLazy(
            function() use ($transaction) {
                return $transaction->commit();
            },
            function($error) use ($transaction) {
                $this->printException($error);
                return $transaction->rollBack()->then(
                    function() use ($error) { throw $error; },
                    function($e) { throw $e; }
                );
            }
        );
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * The default implementation throws an exception indicating the migration cannot be removed.
     * Child classes may override this method if the corresponding migrations can be removed.
     * @return ExtendedPromiseInterface
     * Resolved promise mean the migration succeeds and rejected - not.
     */
    public function down()
    {
        $transaction = $this->db->createTransaction();
        return $transaction->begin()->thenLazy(
            function($connection) {
                $promise = $this->safeDown($connection);
                return is_array($promise) ? allInOrder($promise) : $promise;
            }
        )->thenLazy(
            function() use ($transaction) {
                return $transaction->commit();
            },
            function($error) use ($transaction) {
                $this->printException($error);
                return $transaction->rollBack()->then(
                    function() use ($error) { throw $error; },
                    function($e) { throw $e; }
                );
            }
        );
    }

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * Note: Not all DBMS support transactions. And some DB queries cannot be put into a transaction. For some examples,
     * please refer to [implicit commit](http://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html).
     *
     * @param ConnectionInterface $connection
     * @return ExtendedPromiseInterface|ExtendedPromiseInterface[] Promise that migration succeeded
     * Resolved promise mean the migration succeeds and rejected - not.
     */
    public function safeUp(ConnectionInterface $connection)
    {
        return reject(new Exception("Empty ::safeUp() migration method"));
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * Note: Not all DBMS support transactions. And some DB queries cannot be put into a transaction. For some examples,
     * please refer to [implicit commit](http://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html).
     *
     * @param ConnectionInterface $connection
     * @return ExtendedPromiseInterface|ExtendedPromiseInterface[] Promise that migration rollback succeeded
     * Resolved promise mean the migration succeeds and rejected - not.
     */
    public function safeDown(ConnectionInterface $connection)
    {
        return reject(new Exception("Empty ::safeDown() migration method"));
    }

    /**
     * @param \Throwable|\Exception $e
     */
    protected function printException($e)
    {
        echo 'Exception: ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ")\n";
        echo $e->getTraceAsString() . "\n";
    }

    /**
     * Executes a SQL statement.
     * This method executes the specified SQL statement using [[db]].
     * @param string $sql the SQL statement to be executed
     * @param array  $params input parameters (name => value) for the SQL execution.
     * See [[Command::execute()]] for more details.
     * @return LazyPromiseInterface
     */
    public function execute($sql, $params = [])
    {
        $sqlOutput = $sql;
        if ($this->maxSqlOutputLength !== null) {
            $sqlOutput = StringHelper::truncate($sql, $this->maxSqlOutputLength, '[... hidden]');
        }

        $cmdPromise = $this->db->createCommand($sql)->bindValues($params)->execute();
        return $this->execPromise($cmdPromise, "execute SQL: $sqlOutput");
    }

    /**
     * Creates and executes an INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     * @param string $table the table that new rows will be inserted into.
     * @param array  $columns the column data (name => value) to be inserted into the table.
     * @return LazyPromiseInterface
     */
    public function insert($table, $columns)
    {
        $cmdPromise = $this->db->createCommand()->insert($table, $columns)->execute();
        return $this->execPromise($cmdPromise, "insert into $table");
    }

    /**
     * Creates and executes a batch INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names.
     * @param array $rows the rows to be batch inserted into the table
     * @return LazyPromiseInterface
     */
    public function batchInsert($table, $columns, $rows)
    {
        $cmdPromise = $this->db->createCommand()->batchInsert($table, $columns, $rows)->execute();
        return $this->execPromise($cmdPromise, "insert into $table");
    }

    /**
     * Creates and executes a command to insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or update them if they do.
     *
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * @param string $table the table that new rows will be inserted into/updated in.
     * @param array|Query $insertColumns the column data (name => value) to be inserted into the table or instance
     * of [[Query]] to perform `INSERT INTO ... SELECT` SQL statement.
     * @param array|bool $updateColumns the column data (name => value) to be updated if they already exist.
     * If `true` is passed, the column data will be updated to match the insert column data.
     * If `false` is passed, no update will be performed if the column data already exists.
     * @param array $params the parameters to be bound to the command.
     * @return LazyPromiseInterface
     */
    public function upsert($table, $insertColumns, $updateColumns = true, $params = [])
    {
        $cmdPromise = $this->db->createCommand()->upsert($table, $insertColumns, $updateColumns, $params)->execute();
        return $this->execPromise($cmdPromise, "upsert into $table");
    }

    /**
     * Creates and executes an UPDATE SQL statement.
     * The method will properly escape the column names and bind the values to be updated.
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param array|string $condition the conditions that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify conditions.
     * @param array $params the parameters to be bound to the query.
     * @return LazyPromiseInterface
     */
    public function update($table, $columns, $condition = '', $params = [])
    {
        $cmdPromise = $this->db->createCommand()->update($table, $columns, $condition, $params)->execute();
        return $this->execPromise($cmdPromise, "update $table");
    }

    /**
     * Creates and executes a DELETE SQL statement.
     * @param string $table the table where the data will be deleted from.
     * @param array|string $condition the conditions that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify conditions.
     * @param array $params the parameters to be bound to the query.
     * @return LazyPromiseInterface
     */
    public function delete($table, $condition = '', $params = [])
    {
        $cmdPromise = $this->db->createCommand()->delete($table, $condition, $params)->execute();
        return $this->execPromise($cmdPromise, "delete from $table");
    }

    /**
     * Builds and executes a SQL statement for creating a new DB table.
     *
     * The columns in the new  table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     *
     * The [[QueryBuilder::getColumnType()]] method will be invoked to convert any abstract type into a physical one.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * put into the generated SQL.
     *
     * @param string $table the name of the table to be created. The name will be properly quoted by the method.
     * @param array $columns the columns (name => definition) in the new table.
     * @param string $options additional SQL fragment that will be appended to the generated SQL.
     * @return LazyPromiseInterface
     */
    public function createTable($table, $columns, $options = null)
    {
        $promises = [];
        $promises[] = $this->db->createCommand()->createTable($table, $columns, $options)->execute();
        foreach ($columns as $column => $type) {
            if ($type instanceof ColumnSchemaBuilder && $type->comment !== null) {
                $promises[] = $this->db->createCommand()->addCommentOnColumn($table, $column, $type->comment)->execute();
            }
        }

        return $this->execPromise($promises, "create table $table");
    }

    /**
     * Builds and executes a SQL statement for renaming a DB table.
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function renameTable($table, $newName)
    {
        $cmdPromise = $this->db->createCommand()->renameTable($table, $newName)->execute();
        return $this->execPromise($cmdPromise, "rename table $table to $newName");
    }

    /**
     * Builds and executes a SQL statement for dropping a DB table.
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function dropTable($table)
    {
        $cmdPromise = $this->db->createCommand()->dropTable($table)->execute();
        return $this->execPromise($cmdPromise, "drop table $table");
    }

    /**
     * Builds and executes a SQL statement for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function truncateTable($table)
    {
        $cmdPromise = $this->db->createCommand()->truncateTable($table)->execute();
        return $this->execPromise($cmdPromise, "truncate table $table");
    }

    /**
     * Builds and executes a SQL statement for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     * @return LazyPromiseInterface
     */
    public function addColumn($table, $column, $type)
    {
        $promises = [];
        $promises[] = $this->db->createCommand()->addColumn($table, $column, $type)->execute();
        if ($type instanceof ColumnSchemaBuilder && $type->comment !== null) {
            $promises[] = $this->db->createCommand()->addCommentOnColumn($table, $column, $type->comment)->execute();
        }

        return $this->execPromise($promises, "add column $column $type to table $table");
    }

    /**
     * Builds and executes a SQL statement for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function dropColumn($table, $column)
    {
        $cmdPromise = $this->db->createCommand()->dropColumn($table, $column)->execute();
        return $this->execPromise($cmdPromise, "drop column $column from table $table");
    }

    /**
     * Builds and executes a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function renameColumn($table, $name, $newName)
    {
        $cmdPromise = $this->db->createCommand()->renameColumn($table, $name, $newName)->execute();
        return $this->execPromise($cmdPromise, "rename column $name in table $table to $newName");
    }

    /**
     * Builds and executes a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     * @return LazyPromiseInterface
     */
    public function alterColumn($table, $column, $type)
    {
        $promises = [];
        $promises[] = $this->db->createCommand()->alterColumn($table, $column, $type)->execute();
        if ($type instanceof ColumnSchemaBuilder && $type->comment !== null) {
            $promises[] = $this->db->createCommand()->addCommentOnColumn($table, $column, $type->comment)->execute();
        }
        return $this->execPromise($promises, "alter column $column in table $table to $type");
    }

    /**
     * Builds and executes a SQL statement for creating a primary key.
     * The method will properly quote the table and column names.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return LazyPromiseInterface
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        $description = "add primary key $name on $table (" . (is_array($columns) ? implode(',', $columns) : $columns) . ')';
        $cmdPromise = $this->db->createCommand()->addPrimaryKey($name, $table, $columns)->execute();
        return $this->execPromise($cmdPromise, $description);
    }

    /**
     * Builds and executes a SQL statement for dropping a primary key.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return LazyPromiseInterface
     */
    public function dropPrimaryKey($name, $table)
    {
        $cmdPromise = $this->db->createCommand()->dropPrimaryKey($name, $table)->execute();
        return $this->execPromise($cmdPromise, "drop primary key $name");
    }

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas or use an array.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas or use an array.
     * @param string $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return LazyPromiseInterface
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $description = "add foreign key $name: $table (" . implode(',', (array) $columns) . ") references $refTable (" . implode(',', (array) $refColumns) . ')';
        $cmdPromise = $this->db->createCommand()->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update)->execute();
        return $this->execPromise($cmdPromise, $description);
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function dropForeignKey($name, $table)
    {
        $cmdPromise = $this->db->createCommand()->dropForeignKey($name, $table)->execute();
        return $this->execPromise($cmdPromise, "drop foreign key $name from table $table");
    }

    /**
     * Builds and executes a SQL statement for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
     * by commas or use an array. Each column name will be properly quoted by the method. Quoting will be skipped for column names that
     * include a left parenthesis "(".
     * @param bool $unique whether to add UNIQUE constraint on the created index.
     * @return LazyPromiseInterface
     */
    public function createIndex($name, $table, $columns, $unique = false)
    {
        $description = 'create' . ($unique ? ' unique' : '') . " index $name on $table (" . implode(',', (array) $columns) . ')';
        $cmdPromise = $this->db->createCommand()->createIndex($name, $table, $columns, $unique)->execute();
        return $this->execPromise($cmdPromise, $description);
    }

    /**
     * Builds and executes a SQL statement for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function dropIndex($name, $table)
    {
        $cmdPromise = $this->db->createCommand()->dropIndex($name, $table)->execute();
        return $this->execPromise($cmdPromise, "drop index $name on $table");
    }

    /**
     * Builds and execute a SQL statement for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function addCommentOnColumn($table, $column, $comment)
    {
        $cmdPromise = $this->db->createCommand()->addCommentOnColumn($table, $column, $comment)->execute();
        return $this->execPromise($cmdPromise, "add comment on column $column");
    }

    /**
     * Builds a SQL statement for adding comment to table.
     *
     * @param string $table the table to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function addCommentOnTable($table, $comment)
    {
        $cmdPromise = $this->db->createCommand()->addCommentOnTable($table, $comment)->execute();
        return $this->execPromise($cmdPromise, "add comment on table $table");
    }

    /**
     * Builds and execute a SQL statement for dropping comment from column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function dropCommentFromColumn($table, $column)
    {
        $cmdPromise = $this->db->createCommand()->dropCommentFromColumn($table, $column)->execute();
        return $this->execPromise($cmdPromise, "drop comment from column $column");
    }

    /**
     * Builds a SQL statement for dropping comment from table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @return LazyPromiseInterface
     */
    public function dropCommentFromTable($table)
    {
        $cmdPromise = $this->db->createCommand()->dropCommentFromTable($table)->execute();
        return $this->execPromise($cmdPromise, "drop comment from table $table");
    }

    /**
     * Prepares for a command to be executed, and outputs to the console.
     *
     * @param string $description the description for the command, to be output to the console.
     * @return float the time before the command is executed, for the time elapsed to be calculated.
     */
    protected function beginCommand($description)
    {
        if (!$this->compact) {
            echo "    > $description ...";
        }
        return microtime(true);
    }

    /**
     * Prepares for a command to be executed, and outputs to the console.
     *
     * @param string $description the description for the command, to be output to the console.
     * @return LazyPromiseInterface with float the time before the command is executed, for the time elapsed to be calculated.
     */
    protected function beginCommandLazy($description) {
        return new LazyPromise(function() use ($description) {
            return resolve($this->beginCommand($description));
        });
    }

    /**
     * Finalizes after the command has been executed, and outputs to the console the time elapsed.
     *
     * @param float $time the time before the command was executed.
     * @return bool
     */
    protected function endCommand($time)
    {
        if (!$this->compact) {
            echo ' done (time: ' . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        }
        return true;
    }

    /**
     * Execute command promise with description printing and time elapsed
     *
     * @param ExtendedPromiseInterface|ExtendedPromiseInterface[] $cmdPromise
     * @param string $description
     * @return LazyPromiseInterface
     */
    protected function execPromise($cmdPromise, $description)
    {
        return $this->beginCommandLazy($description)->thenLazy(
            function($time) use ($cmdPromise) {
                if (is_array($cmdPromise)) {
                    $cmdPromise = allInOrder($cmdPromise);
                }
                return $cmdPromise->always(function() use ($time) {
                    return $this->endCommand($time);
                });
            }
        );
    }
}
