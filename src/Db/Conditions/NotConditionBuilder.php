<?php

namespace Reaction\Db\Conditions;

use Reaction\Db\Expressions\ExpressionBuilderInterface;
use Reaction\Db\Expressions\ExpressionBuilderTrait;
use Reaction\Db\Expressions\ExpressionInterface;

/**
 * Class NotConditionBuilder builds objects of [[NotCondition]]
 */
class NotConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|NotCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        $operand = $expression->getCondition();
        if ($operand === '') {
            return '';
        }

        $expression = $this->queryBuilder->buildCondition($operand, $params);
        return "{$this->getNegationOperator()} ($expression)";
    }

    /**
     * @return string
     */
    protected function getNegationOperator()
    {
        return 'NOT';
    }
}
