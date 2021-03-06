<?php

namespace Reaction\Web\Sessions;

use Reaction\Base\ComponentAutoloadInterface;
use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\Base\RequestAppComponentInterface;

/**
 * Interface RequestSessionInterface
 * @package Reaction\Web
 * @property array $data
 */
interface RequestSessionInterface extends RequestAppComponentInterface, ComponentAutoloadInterface, ComponentInitBlockingInterface
{
    /**
     * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     */
    public function get($key, $defaultValue = null);

    /**
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed $value session variable value
     */
    public function set($key, $value);

    /**
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     * @return mixed the removed value, null if no such session variable.
     */
    public function remove($key);

    /**
     * Removes all session variables.
     */
    public function removeAll();

    /**
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     */
    public function has($key);
}