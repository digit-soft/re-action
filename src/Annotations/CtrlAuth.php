<?php

namespace Reaction\Annotations;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\RequestApplicationInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
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
        if ($this->authorized && $app->user->getIsGuest()) {
            return reject(false);
        }
        if (!empty($this->permissions)) {
            $promises = [];
            foreach ($this->permissions as $permissionName) {
                $promises[] = $app->user->can($permissionName);
            }
            return !empty($promises)
                ? \Reaction\Promise\any($promises)
                : reject(false);
        }
        return resolve(true);
    }
}