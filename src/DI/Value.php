<?php

namespace Reaction\DI;

use Reaction\Exceptions\InvalidConfigException;

/**
 * Value getter through callback function
 */
class Value
{
    /** @var callable|array */
    public $callback;

    /**
     * Constructor.
     * @param string $callback factory callback that receives as parameter \Reaction\DI\Container instance
     */
    protected function __construct($callback)
    {
        $this->callback = $callback;
    }

    /**
     * Creates a new Value object.
     * @param callable|array $callback factory callback that receives as parameter \Reaction\DI\Container instance
     * @return Value the new Value (Factory) object.
     */
    public static function of($callback)
    {
        return new static($callback);
    }

    /**
     * Restores class state after using `var_export()`.
     *
     * @param array $state
     * @return Value
     * @throws InvalidConfigException when $state property does not contain `id` parameter
     * @see var_export()
     */
    public static function __set_state($state)
    {
        if (!isset($state['callback'])) {
            throw new InvalidConfigException('Failed to instantiate class "Factory". Required parameter "callback" is missing');
        }

        return new self($state['callback']);
    }
}