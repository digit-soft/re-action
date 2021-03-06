<?php

namespace Reaction\Web\Sessions;

use Reaction\Promise\ExtendedPromiseInterface;

/**
 * Interface SessionArchiveInterface
 * @package Reaction\Web\Sessions
 */
interface SessionArchiveInterface
{
    /**
     * Get data from archive
     * @param string $id Session id
     * @param bool   $remove Flag to remove data
     * @return ExtendedPromiseInterface with data array
     */
    public function get($id, $remove = true);

    /**
     * Save data to archive
     * @param string $id
     * @param array  $data
     * @return ExtendedPromiseInterface which resolved after save complete
     */
    public function set($id, $data);

    /**
     * Check that session exists in archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface
     */
    public function exists($id);

    /**
     * Remove from archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface which resolved when process complete
     */
    public function remove($id);

    /**
     * Garbage collector callback
     * @param int $lifeTime Session life time in archive
     * @return ExtendedPromiseInterface which resolved after process complete
     */
    public function gc($lifeTime = 3600);
}