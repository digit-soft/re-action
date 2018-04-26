<?php

namespace Reaction\Web\Sessions;

use React\Promise\ExtendedPromiseInterface;
use Reaction\Web\AppRequestInterface;

/**
 * Interface SessionHandlerInterface
 * @package Reaction\Web\Sessions
 */
interface SessionHandlerInterface
{
    /**
     * Read session data and returns serialized|encoded data
     * @param string $sessionId
     * @return ExtendedPromiseInterface  with session data
     */
    public function read($sessionId);

    /**
     * Write session data to storage
     * @param string $sessionId
     * @param array  $data
     * @return ExtendedPromiseInterface  with session data
     */
    public function write($sessionId, $data);

    /**
     * Destroy a session
     * @param string $sessionId The session ID being destroyed.
     * @return ExtendedPromiseInterface  with bool when finished
     */
    public function destroy($sessionId);

    /**
     * Regenerate session id
     * @param string $sessionIdOld
     * @return ExtendedPromiseInterface With new session ID (string)
     */
    public function regenerateId($sessionIdOld, AppRequestInterface $request);

    /**
     * Check session id for uniqueness
     * @param string $sessionId
     * @return bool
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
     * @return ExtendedPromiseInterface  with bool
     */
    public function archiveSessionData($sessionId, $data);

    /**
     * Restore session data from archive
     * @param string $sessionId
     * @return ExtendedPromiseInterface  with session data array or null
     */
    public function restoreSessionData($sessionId);

    /**
     * Return a new session ID
     * @param AppRequestInterface $request
     * @return string  A session ID valid for session handler
     */
    public function createSid(AppRequestInterface $request);

    /**
     * Update timestamp of a session
     * @param string $sessionId The session id
     * @param string $data
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * @return bool
     */
    public function updateTimestamp($sessionId, $data);
}