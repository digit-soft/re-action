<?php

namespace Reaction\Console\Routes;

use Reaction\RequestApplicationInterface;

/**
 * Class ErrorController
 * @package Reaction\Console\Controllers
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
     */
    public function actionError(RequestApplicationInterface $app, \Throwable $exception) {
        \Reaction::error($exception);
//        return $this->render($app, 'view', [
//            'exception' => $exception,
//            'exceptionName' => $this->getExceptionName($exception),
//        ]);
    }
}