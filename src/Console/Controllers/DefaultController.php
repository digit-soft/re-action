<?php

namespace Reaction\Console\Controllers;

use Reaction\Console\Routes\Controller;
use Reaction\Helpers\Console;

class DefaultController extends Controller
{
    public function actionIndex() {
        $this->stdout("Default page\n", Console::FG_BLUE);
    }
}