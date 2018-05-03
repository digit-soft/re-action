<?php

namespace Reaction\Routes;

use Reaction\Web\AppRequestInterface;

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
     * @param AppRequestInterface $request
     * @param \Throwable          $exception
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function actionError(AppRequestInterface $request, \Throwable $exception) {
        return $this->render($request, 'view', [
            'exception' => $exception,
            'exceptionName' => $this->getExceptionName($exception),
        ]);
    }
}