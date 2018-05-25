<?php

namespace Reaction\Console\Controllers;

use React\EventLoop\Timer\TimerInterface;
use Reaction\Console\Routes\Controller;
use Reaction\Helpers\Console;
use Reaction\Helpers\ReflectionHelper;
use Reaction\Promise\Promise;
use Reaction\Routes\ControllerInterface;

/**
 * Class MigrateController
 * @package Reaction\Console\Controllers
 */
class MigrateController extends Controller
{
    public function actionTest() {
        \Reaction::warning(ReflectionHelper::getMethodReflection($this, 'actionTest'));
        return new Promise(function($r, $c) {
            $n = 1;
            Console::startProgress(0, 100, 'Counting objects: ');
            \Reaction::$app->loop->addPeriodicTimer(0.05, function(TimerInterface $timer) use (&$n, $r) {
                //\Reaction::warning(get_class($timer));
                Console::updateProgress($n, 100);
                $n++;
                if ($n > 100) {
                    Console::endProgress("done." . PHP_EOL);
                    $r(null);
                    $timer->cancel();
                }
            });
        });
    }
}