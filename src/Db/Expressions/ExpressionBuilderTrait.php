<?php

namespace Reaction\Db\Expressions;

use Reaction\Db\QueryBuilder;

/**
 * Trait ExpressionBuilderTrait provides common constructor for classes that
 * should implement [[ExpressionBuilderInterface]]
 */
trait ExpressionBuilderTrait
{
    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * ExpressionBuilderTrait constructor.
     *
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
}
