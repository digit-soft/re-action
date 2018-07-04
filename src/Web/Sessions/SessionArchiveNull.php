<?php

namespace Reaction\Web\Sessions;

use Reaction\Exceptions\SessionException;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * Class SessionArchiveNull
 * @package Reaction\Web\Sessions
 */
class SessionArchiveNull extends SessionArchiveAbstract
{

    /**
     * Get data from archive
     * @param string $id Session id
     * @param bool   $remove Flag to remove data
     * @return ExtendedPromiseInterface with data array
     */
    public function get($id, $remove = true)
    {
        return reject($this->getNullError());
    }

    /**
     * Save data to archive
     * @param string $id
     * @param array  $data
     * @return ExtendedPromiseInterface which resolved after save complete
     */
    public function set($id, $data)
    {
        return reject($this->getNullError());
    }

    /**
     * Check that session exists in archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface
     */
    public function exists($id)
    {
        return reject($this->getNullError());
    }

    /**
     * Remove from archive
     * @param string $id Session id
     * @return ExtendedPromiseInterface which resolved when process complete
     */
    public function remove($id)
    {
        return resolve(true);
    }

    /**
     * Garbage collector callback
     * @param int $lifeTime Session life time in archive
     * @return ExtendedPromiseInterface which resolved after process complete
     */
    public function gc($lifeTime = 3600)
    {
        return resolve(true);
    }

    private function getNullError()
    {
        return new SessionException("Session archive is disabled");
    }
}