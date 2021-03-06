<?php

namespace Reaction\Base;

use React\Promise\PromiseInterface;

/**
 * Interface ComponentInitBlockingInterface.
 * Services/components implementing this interface will be initialized
 * and parent container will wait while this operation completes
 * @package Reaction\Base
 */
interface ComponentInitBlockingInterface
{
    /**
     * Init callback. Called by parent container/service/component on init and must return a fulfilled Promise
     * @return PromiseInterface
     */
    public function initComponent();

    /**
     * Check that component was initialized earlier
     * @return bool
     */
    public function isInitialized();
}