<?php

namespace Reaction\Db\Conditions;

use Reaction\Db\Expressions\ExpressionBuilderInterface;
use Reaction\Db\Expressions\ExpressionBuilderTrait;

/**
 * Class ExistsConditionBuilder builds objects of [[ExistsCondition]]
 */
class ExistsConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExistsCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build($expression, array &$params = [])
    {
        $operator = $expression->getOperator();
        $query = $expression->getQuery();

        $sql = $this->queryBuilder->buildExpression($query, $params);

        return "$operator $sql";
    }
}
