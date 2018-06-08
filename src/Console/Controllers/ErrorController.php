<?php

namespace Reaction\Console\Controllers;

use Reaction\Console\Routes\Controller;
use Reaction\RequestApplicationInterface;
use Reaction\Routes\ControllerInternalInterface;

/**
 * Controller used to print errors
 * @package Reaction\Console\Controllers
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
     * Show error to user
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     */
    public function actionError(RequestApplicationInterface $app, \Throwable $exception) {
        $logMessage = get_class($exception);
        if ($exception->getMessage()) {
            $logMessage .= "\n" . $exception->getMessage();
        }
        if ($exception->getFile() || $exception->getLine()) {
            $logMessage .= "\n" . $exception->getFile() . ":" . $exception->getLine();
        }
        \Reaction::$app->logger->logRaw($logMessage);
    }
}