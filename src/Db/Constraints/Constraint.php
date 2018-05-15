<?php

namespace Reaction\Db\Constraints;

use Reaction\Base\BaseObject;

/**
 * Constraint represents the metadata of a table constraint.
 */
class Constraint extends BaseObject
{
    /**
     * @var string[]|null list of column names the constraint belongs to.
     */
    public $columnNames;
    /**
     * @var string|null the constraint name.
     */
    public $name;
}
