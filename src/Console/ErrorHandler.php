<?php

namespace Reaction\Console;

use Reaction;
use Reaction\Exceptions\ErrorException;
use Reaction\Exceptions\UserException;
use Reaction\Helpers\Console;

/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 *
 * ErrorHandler is configured as an application component in [[\Reaction\RequestApplicationInterface]] by default.
 * You can access that instance via `$app->errorHandler`.
 */
class ErrorHandler extends \Reaction\Base\ErrorHandler
{
    /**
     * Logs the given exception.
     * @param \Exception $exception the exception to be logged
     */
    public function logException($exception)
    {
    }

    /**
     * Renders an exception using ansi format for console output.
     * @param \Exception $exception the exception to be rendered.
     */
    protected function renderException($exception)
    {
        if ($exception instanceof UnknownCommandException) {
            // display message and suggest alternatives in case of unknown command
            $message = $this->formatMessage($exception->getName() . ': ') . $exception->command;
            $alternatives = $exception->getSuggestedAlternatives();
            if (count($alternatives) === 1) {
                $message .= "\n\nDid you mean \"" . reset($alternatives) . '"?';
            } elseif (count($alternatives) > 1) {
                $message .= "\n\nDid you mean one of these?\n    - " . implode("\n    - ", $alternatives);
            }
        } elseif ($exception instanceof Exception && ($exception instanceof UserException || !Reaction::isDebug())) {
            $message = $this->formatMessage($exception->getName() . ': ') . $exception->getMessage();
        } elseif (Reaction::isDebug()) {
            if ($exception instanceof Exception) {
                $message = $this->formatMessage("Exception ({$exception->getName()})");
            } elseif ($exception instanceof ErrorException) {
                $message = $this->formatMessage($exception->getName());
            } else {
                $message = $this->formatMessage('Exception');
            }
            $message .= $this->formatMessage(" '" . get_class($exception) . "'", [Console::BOLD, Console::FG_BLUE])
                . ' with message ' . $this->formatMessage("'{$exception->getMessage()}'", [Console::BOLD]) //. "\n"
                . "\n\nin " . dirname($exception->getFile()) . DIRECTORY_SEPARATOR . $this->formatMessage(basename($exception->getFile()), [Console::BOLD])
                . ':' . $this->formatMessage($exception->getLine(), [Console::BOLD, Console::FG_YELLOW]) . "\n";
            $trace = static::getExceptionTrace($exception, true);
            //$trace = $exception->getTraceAsString();
            $message .= "\n" . $this->formatMessage("Stack trace:\n", [Console::BOLD]) . $trace;
        } else {
            $message = $this->formatMessage('Error: ') . $exception->getMessage();
        }

        if (Reaction::isConsoleApp()) {
            Console::stderr($message . "\n");
        } else {
            echo $message . "\n";
        }
    }

    /**
     * Colorizes a message for console output.
     * @param string $message the message to colorize.
     * @param array $format the message format.
     * @return string the colorized message.
     * @see Console::ansiFormat() for details on how to specify the message format.
     */
    protected function formatMessage($message, $format = [Console::FG_RED, Console::BOLD])
    {
        $stream = Reaction::isConsoleApp() ? \STDERR : \STDOUT;
        // try controller first to allow check for --color switch
        if (Reaction::isConsoleApp() && Console::streamSupportsAnsiColors($stream)) {
            $message = Console::ansiFormat($message, $format);
        }

        return $message;
    }
}
