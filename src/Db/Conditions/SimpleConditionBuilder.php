<?php

namespace Reaction\Db\Conditions;

use Reaction\Db\Expressions\ExpressionBuilderInterface;
use Reaction\Db\Expressions\ExpressionBuilderTrait;
use Reaction\Db\Expressions\ExpressionInterface;

/**
 * Class NotConditionBuilder builds objects of [[SimpleCondition]]
 */
class SimpleConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param SimpleCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build($expression, array &$params = [])
    {
        $operator = $expression->getOperator();
        $column = $expression->getColumn();
        $value = $expression->getValue();

        if (strpos($column, '(') === false) {
            $column = $this->queryBuilder->db->quoteColumnName($column);
        }

        if ($value === null) {
            return "$column $operator NULL";
        }
        if ($value instanceof ExpressionInterface) {
            return "$column $operator {$this->queryBuilder->buildExpression($value, $params)}";
        }

        $phName = $this->queryBuilder->bindParam($value, $params);
        return "$column $operator $phName";
    }
}
