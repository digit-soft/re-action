<?php

namespace Reaction\Web\Sessions;

use Reaction\Db\CommandInterface;
use Reaction\Db\ConnectionInterface;
use Reaction\Db\DatabaseInterface;
use Reaction\Db\Query;
use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Class SessionArchiveInDb
 * @package Reaction\Web\Sessions
 */
class SessionArchiveInDb extends SessionArchiveAbstract
{
    /**
     * @var string Db table name
     */
    public $table = '{{%session_archive}}';
    /**
     * @var DatabaseInterface|string Database used
     */
    public $db = 'db';

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!isset($this->db)) {
            $this->db = \Reaction::$app->getDb();
        } elseif (!is_object($this->db)) {
            $this->db = \Reaction::$app->get($this->db);
        }
    }

    /**
     * Get data from archive
     * @param string $id Session id
     * @param bool   $remove Flag to remove data
     * @return ExtendedPromiseInterface with data array
     */
    public function get($id, $remove = false)
    {
        return $this->getInternal($id);
    }

    /**
     * Save data to archive
     * @param string $id
     * @param array  $data
     * @return ExtendedPromiseInterface which resolved after save complete
     */
    public function set($id, $data)
    {
        $dataSerialized = $this->getHandler()->serializeData($data);
        $timestamp = time();
        $row = [
            'sid' => $id,
            'data' => $dataSerialized,
            'updated_at' => $timestamp,
        ];
        return $this->getInternal($id, false)
            ->then(function($existingRow) use ($row) {
                return $this->updateInternal($row, $existingRow);
            }, function() use ($row) {
                return $this->insertInternal($row);
            })->then(function() {
                return true;
            });
    }

    /**
     * Check that session exists in archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface
     */
    public function exists($id)
    {
        return $this->selectQuery()->where(['sid' => $id])->exists($this->db);
    }

    /**
     * Remove from archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface which resolved when process complete
     */
    public function remove($id)
    {
        return $this->createCommand()
            ->delete($this->table, ['sid' => $id])
            ->execute();
    }

    /**
     * Garbage collector callback
     * @param int $lifeTime Session life time in archive
     * @return ExtendedPromiseInterface which resolved after process complete
     */
    public function gc($lifeTime = 3600)
    {
        $expiredTs = time() - $lifeTime;
        return $this->createCommand()
            ->delete($this->table, ['updated_at' => $expiredTs])
            ->execute();
    }

    /**
     * Create SELECT Query
     * @return Query
     */
    protected function selectQuery()
    {
        return (new Query())->from($this->table);
    }

    /**
     * Create command
     * @param string|null              $sql
     * @param array                    $params
     * @param ConnectionInterface|null $connection
     * @return CommandInterface
     */
    protected function createCommand($sql = null, $params = [], $connection = null)
    {
        return $this->db->createCommand($sql = null, $params = [], $connection = null);
    }

    /**
     * Get entry from DB
     * @param string $id
     * @param bool $unserialize
     * @return ExtendedPromiseInterface
     */
    protected function getInternal($id, $unserialize = true)
    {
        return $this->selectQuery()->where(['sid' => $id])
            ->one($this->db)
            ->then(function($row) use ($unserialize) {
                return $unserialize ? $this->getHandler()->unserializeData($row['data']) : $row;
            });
    }

    /**
     * Insert new entry to DB
     * @param array $row
     * @return ExtendedPromiseInterface|\Reaction\Promise\LazyPromiseInterface
     */
    protected function insertInternal($row)
    {
        return $this->createCommand()
            ->insert($this->table, $row)
            ->execute();
    }

    /**
     * Update existing entry in DB
     * @param array      $row
     * @param array|null $existingRow
     * @return ExtendedPromiseInterface|\Reaction\Promise\LazyPromiseInterface
     */
    protected function updateInternal($row, $existingRow = null)
    {
        $equal = isset($existingRow) && isset($existingRow['data']) && $row['data'] === $existingRow['data'];
        $id = $row['sid'];
        unset($row['sid']);
        //If data not changed then just update timestamp
        if ($equal) {
            unset($row['data']);
        }
        return $this->createCommand()
            ->update($this->table, $row, ['sid' => $id])
            ->execute();
    }
}