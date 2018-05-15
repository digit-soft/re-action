<?php

namespace Reaction\Db\Expressions;

/**
 * Class ExpressionBuilder builds objects of [[yii\db\Expression]] class.
 */
class ExpressionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * {@inheritdoc}
     * @param Expression|ExpressionInterface $expression the expression to be built
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        $params = array_merge($params, $expression->params);
        return $expression->__toString();
    }
}
