<?php

namespace Reaction\Routes\StdControllers;

use Reaction\Annotations\Ctrl;
use Reaction\Annotations\CtrlAction;
use Reaction\RequestApplicationInterface;
use Reaction\Routes\Controller;
use Reaction\Routes\ControllerInternalInterface;

/**
 * @Ctrl(group="/error")
 * Class ErrorController. This controller for internal usage only (to render errors)
 * @package Reaction\Routes
 */
class ErrorController extends Controller implements ControllerInternalInterface
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
     * @CtrlAction(path="/error")
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     * @return \Reaction\Web\ResponseBuilderInterface|string
     */
    public function actionError(RequestApplicationInterface $app, \Throwable $exception) {
        return $this->render($app, 'view', [
            'exception' => $exception,
            'exceptionName' => $this->getExceptionName($exception),
        ], true);
    }
}