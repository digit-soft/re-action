<?php

namespace Reaction\Annotations;

use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Web\AppRequestInterface;

/**
 * Interface CtrlActionValidatorInterface.
 * Annotations, those implements this interface can validate access to controller action
 * @package Reaction\Annotations
 */
interface CtrlActionValidatorInterface
{
    /**
     * Validation callback
     * @param AppRequestInterface $request
     * @return ExtendedPromiseInterface
     */
    public function validate(AppRequestInterface $request);
}