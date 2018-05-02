<?php

namespace Reaction\Web\Sessions;

use React\Promise\PromiseInterface;
use Reaction\Web\AppRequestInterface;

/**
 * Interface SessionHandlerInterface
 * @package Reaction\Web\Sessions
 */
interface SessionHandlerInterface
{
    /**
     * Read session data and returns serialized|encoded data
     * @param string $id
     * @return PromiseInterface  with session data
     */
    public function read($id);

    /**
     * Write session data to storage
     * @param string $id
     * @param array  $data
     * @return PromiseInterface  with session data
     */
    public function write($id, $data);

    /**
     * Destroy a session
     * @param string $id The session ID being destroyed.
     * @return PromiseInterface  with bool when finished
     */
    public function destroy($id);

    /**
     * Regenerate session id
     * @param string              $idOld
     * @param AppRequestInterface $request
     * @param bool                $deleteOldSession
     * @return PromiseInterface With new session ID (string)
     */
    public function regenerateId($idOld, AppRequestInterface $request, $deleteOldSession = false);

    /**
     * Check session id for uniqueness
     * @param string $sessionId
     * @return PromiseInterface
     */
    public function checkSessionId($sessionId);

    /**
     * Cleanup old sessions. Timer callback.
     * Sessions that have not updated for the last '$this->gcLifetime' seconds will be removed.
     * @return void
     */
    public function gc();

    /**
     * Archive session and free main storage
     * @param string $sessionId
     * @param array  $data
     * @return PromiseInterface  with bool
     */
    public function archiveSessionData($sessionId, $data);

    /**
     * Restore session data from archive
     * @param string $sessionId
     * @param bool   $deleteFromArchive
     * @return PromiseInterface  with session data array or null
     */
    public function restoreSessionData($sessionId, $deleteFromArchive = false);

    /**
     * Return a new session ID
     * @param AppRequestInterface $request
     * @return PromiseInterface  A session ID valid for session handler
     */
    public function createId(AppRequestInterface $request);

    /**
     * Update timestamp of a session
     * @param string $id The session id
     * @param string $data
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * @return PromiseInterface  with bool when finished
     */
    public function updateTimestamp($id, $data);
}