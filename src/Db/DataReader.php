<?php

namespace Reaction\Db;

use Reaction\Exceptions\InvalidCallException;

/**
 * DataReader represents a forward-only stream of rows from a query result set.
 *
 * To read the current row of data, call [[read()]]. The method [[readAll()]]
 * returns all the rows in a single array. Rows of data can also be read by
 * iterating through the reader. For example,
 *
 * ```php
 * $command = $connection->createCommand('SELECT * FROM post');
 * $reader = $command->query();
 *
 * while ($row = $reader->read()) {
 *     $rows[] = $row;
 * }
 *
 * // equivalent to:
 * foreach ($reader as $row) {
 *     $rows[] = $row;
 * }
 *
 * // equivalent to:
 * $rows = $reader->readAll();
 * ```
 *
 * Note that since DataReader is a forward-only stream, you can only traverse it once.
 * Doing it the second time will throw an exception.
 *
 * It is possible to use a specific mode of data fetching by setting
 * [[fetchMode]]. See the [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
 * for more details about possible fetch mode.
 *
 * @property int $columnCount The number of columns in the result set. This property is read-only.
 * @property int $fetchMode Fetch mode. This property is write-only.
 * @property bool $isClosed Whether the reader is closed or not. This property is read-only.
 * @property int $rowCount Number of rows contained in the result. This property is read-only.
 */
class DataReader extends \Reaction\Base\BaseObject implements \Iterator, \Countable
{
    private $_closed = false;
    private $_row;
    private $_index = -1;
    private $_results;
    private $_command;
    private $_fetchMode;


    /**
     * Constructor.
     * @param CommandInterface $command
     * @param array            $results Associative array of query execution results
     * @param array            $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct(CommandInterface $command, $results = [], $config = [])
    {
        $this->_results = $results;
        $this->_command = $command;
        $this->fetchMode = CommandInterface::FETCH_MODE_ASSOC;
        $this->rewind();
        parent::__construct($config);
    }

    /**
     * Set the default fetch mode for this statement.
     *
     * @param string $mode fetch mode
     * @see http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
     */
    public function setFetchMode($mode)
    {
        $this->_fetchMode = $mode;
    }

    /**
     * Advances the reader to the next row in a result set.
     * @return array|false the current row, false if no more row available
     */
    public function read()
    {
        $this->next();
        if (!$this->valid()) {
            return false;
        }
        return $this->_command->fetchResultsRow($this->_row, CommandInterface::FETCH_ROW, $this->_fetchMode);
    }

    /**
     * Returns a single column from the next row of a result set.
     * @param int $columnIndex zero-based column index
     * @return mixed the column of the current row, false if no more rows available
     */
    public function readColumn($columnIndex)
    {
        $this->next();
        if (!$this->valid()) {
            return false;
        }
        return $this->_command->fetchResultsRow($this->_row, CommandInterface::FETCH_FIELD, CommandInterface::FETCH_MODE_ASSOC, $columnIndex);
    }

    /**
     * Returns an object populated with the next row of data.
     * @param string $className class name of the object to be created and populated
     * @param array $fields Elements of this array are passed to the constructor
     * @return mixed the populated object, false if no more row of data available
     */
    public function readObject($className = 'stdClass', $fields = [])
    {
        $this->next();
        if (!$this->valid()) {
            return false;
        }
        $obj = new $className($fields);
        foreach ($this->_row as $key => $value) {
            $obj->{$key} = $value;
        }
        return $obj;
    }

    /**
     * Reads the whole result set into an array.
     * @return array the result set (each array element represents a row of data).
     * An empty array will be returned if the result contains no row.
     */
    public function readAll()
    {
        return $this->_command->fetchResults($this->_results, CommandInterface::FETCH_ALL, $this->_fetchMode);
    }

    /**
     * Closes the reader.
     * This frees up the resources allocated for executing this SQL statement.
     * Read attempts after this method call are unpredictable.
     */
    public function close()
    {
        $this->_closed = true;
    }

    /**
     * whether the reader is closed or not.
     * @return bool whether the reader is closed or not.
     */
    public function getIsClosed()
    {
        return $this->_closed;
    }

    /**
     * Returns the number of rows in the result set.
     * Note, most DBMS may not give a meaningful count.
     * In this case, use "SELECT COUNT(*) FROM tableName" to obtain the number of rows.
     * @return int number of rows contained in the result.
     */
    public function getRowCount()
    {
        return !empty($this->_results) ? count($this->_results) : 0;
    }

    /**
     * Returns the number of rows in the result set.
     * This method is required by the Countable interface.
     * Note, most DBMS may not give a meaningful count.
     * In this case, use "SELECT COUNT(*) FROM tableName" to obtain the number of rows.
     * @return int number of rows contained in the result.
     */
    public function count()
    {
        return $this->getRowCount();
    }

    /**
     * Returns the number of columns in the result set.
     * Note, even there's no row in the reader, this still gives correct column number.
     * @return int the number of columns in the result set.
     */
    public function getColumnCount()
    {
        if (empty($this->_results)) {
            return 0;
        }
        $results = $this->_results;
        $firstRow = reset($results);
        return count($firstRow);
    }

    /**
     * Resets the iterator to the initial state.
     * This method is required by the interface [[\Iterator]].
     * @throws InvalidCallException if this method is invoked twice
     */
    public function rewind()
    {
        $this->_index = 0;
        $this->_row = reset($this->_results);
    }

    /**
     * Returns the index of the current row.
     * This method is required by the interface [[\Iterator]].
     * @return int the index of the current row.
     */
    public function key()
    {
        return $this->_index;
    }

    /**
     * Returns the current row.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current row.
     */
    public function current()
    {
        return is_array($this->_row) ? $this->_command->fetchResultsRow($this->_row, CommandInterface::FETCH_ROW, $this->_fetchMode) : $this->_row;
    }

    /**
     * Moves the internal pointer to the next row.
     * This method is required by the interface [[\Iterator]].
     */
    public function next()
    {
        if ($this->_index === -1) {
            $this->_row = reset($this->_results);
        } else {
            $this->_row = next($this->_results);
        }
        $this->_index++;
    }

    /**
     * Returns whether there is a row of data at current position.
     * This method is required by the interface [[\Iterator]].
     * @return bool whether there is a row of data at current position.
     */
    public function valid()
    {
        return $this->_row !== false;
    }
}
