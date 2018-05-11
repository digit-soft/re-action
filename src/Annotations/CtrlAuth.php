<?php

namespace Reaction\Annotations;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\RequestApplicationInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * TODO: Return after Authorization & User complete
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 * @Attributes({
 *   @Attribute("authorized", type = "bool"),
 *   @Attribute("permissions",  type = "array"),
 * })
 *
 * Class Auth. Auth validation for controller action
 * @package Reaction\Annotations
 */
class CtrlAuth implements CtrlActionValidatorInterface
{
    /**
     * @var array Permissions to check
     */
    public $permissions = [];
    /**
     * @var bool Only logged users / anonymous.
     * If 'true' then only logged in users is permitted, 'false' - only anonymous
     */
    public $authorized;

    /**
     * Validation callback
     * @param RequestApplicationInterface $app
     * @return ExtendedPromiseInterface
     */
    public function validate(RequestApplicationInterface $app)
    {
        if (empty($this->permissions) && $this->authorized === null) {
            return resolve(true);
        }
        return reject(false);
    }
}