<?php

namespace Reaction\Db;

use Reaction\Base\BaseObject;

/**
 * Class QueryBuilder
 * @package Reaction\Db
 */
class QueryBuilder extends BaseObject implements QueryBuilderInterface
{
    /** @var DatabaseInterface */
    public $db;
}