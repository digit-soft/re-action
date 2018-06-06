<?php

namespace Reaction\Routes\StdControllers;

use Reaction\Annotations\Ctrl;
use Reaction\Annotations\CtrlAction;
use Reaction\Routes\Controller;
use Reaction\Web\Response;

/**
 * Class AssetsController
 * @package Reaction\Routes\StdControllers
 * @Ctrl(group="/assets")
 */
class AssetsController extends Controller
{
    /**
     * @CtrlAction(path="/",method={"ANY"})
     */
    public function actionIndex()
    {
        $message = 'You must setup proxy server to serve static files. Do not use this framework for that purpose.';
        return new Response(404, [], $message);
    }

    /**
     * @CtrlAction(path="/{anyPath:.+}",method={"ANY"})
     */
    public function actionAny()
    {
        return $this->actionIndex();
    }
}