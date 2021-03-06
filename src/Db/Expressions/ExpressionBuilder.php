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
     * @param Expression $expression the expression to be built
     */
    public function build($expression, array &$params = [])
    {
        $params = array_merge($params, $expression->params);
        return method_exists($expression, '__toString') ? $expression->__toString() : '';
    }
}
