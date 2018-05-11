<?php

namespace Reaction\Annotations;

use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\RequestApplicationInterface;

/**
 * Interface CtrlActionValidatorInterface.
 * Annotations, those implements this interface can validate access to controller action
 * @package Reaction\Annotations
 */
interface CtrlActionValidatorInterface
{
    /**
     * Validation callback
     * @param RequestApplicationInterface $app
     * @return ExtendedPromiseInterface
     */
    public function validate(RequestApplicationInterface $app);
}