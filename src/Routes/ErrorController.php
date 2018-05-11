<?php

namespace Reaction\Routes;

use Reaction\RequestApplicationInterface;

/**
 * Class ErrorController
 * @package Reaction\Routes
 */
class ErrorController extends Controller
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->baseViewPath = \Reaction::$app->getAlias('@reaction/Views');
        parent::init();
    }

    /**
     * Error action default
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function actionError(RequestApplicationInterface $app, \Throwable $exception) {
        return $this->render($app, 'view', [
            'exception' => $exception,
            'exceptionName' => $this->getExceptionName($exception),
        ]);
    }
}