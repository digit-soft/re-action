<?php

namespace Reaction\Base;

use Reaction;
use Reaction\Exceptions\ErrorException;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\HttpException;
use Reaction\Exceptions\UserException;
use Reaction\Helpers\VarDumper;

/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 *
 * ErrorHandler is configured as an application component in [[\Reaction\StaticApplicationInterface]] by default.
 * You can access that instance via `Reaction::$app->errorHandler`.
 *
 * @package Reaction\Base
 */
abstract class ErrorHandler extends RequestAppComponent implements ErrorHandlerInterface
{
    /**
     * @var bool whether to discard any existing page output before error display. Defaults to true.
     */
    public $discardExistingOutput = true;
    /**
     * @var int the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. If you set this value to be 0, no memory will be reserved.
     * Defaults to 256KB.
     */
    public $memoryReserveSize = 262144;
    /**
     * @var \Exception|null the exception that is being handled currently.
     */
    public $exception;

    /**
     * @var string Used to reserve memory for fatal error handler.
     */
    private $_memoryReserve;

    /**
     * Handles uncaught PHP exceptions.
     *
     * This method is implemented as a PHP exception handler.
     *
     * @param \Exception $exception the exception that is not caught
     * @return Reaction\Web\Response
     */
    public function handleException($exception)
    {
        $this->exception = $exception;

        try {
            $this->logException($exception);
            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            return $this->renderException($exception);
        } catch (\Exception $e) {
            // an other exception could be thrown while displaying the exception
            $this->handleFallbackExceptionMessage($e, $exception);
        } catch (\Throwable $e) {
            // additional check for \Throwable introduced in PHP 7
            $this->handleFallbackExceptionMessage($e, $exception);
        }

        $this->exception = null;
        return new Reaction\Web\Response(500, [], 'Server error');
    }

    /**
     * Handles PHP execution errors such as warnings and notices.
     *
     * This method is used as a PHP error handler. It will simply raise an [[ErrorException]].
     *
     * @param int $code the level of the error raised.
     * @param string $message the error message.
     * @param string $file the filename that the error was raised in.
     * @param int $line the line number the error was raised at.
     * @return bool whether the normal error handler continues.
     *
     * @throws ErrorException
     */
    public function handleError($code, $message, $file, $line)
    {
        if (error_reporting() & $code) {
            static::loadErrorExceptionClass();
            $exception = new ErrorException($message, $code, $code, $file, $line);

            // in case error appeared in __toString method we can't throw any exception
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);
            foreach ($trace as $frame) {
                if ($frame['function'] === '__toString') {
                    $this->handleException($exception);
                }
            }

            throw $exception;
        }

        return false;
    }

    /**
     * Handles fatal PHP errors.
     */
    public function handleFatalError()
    {
        unset($this->_memoryReserve);
        static::loadErrorExceptionClass();
        $error = error_get_last();

        if (ErrorException::isFatalError($error)) {
            $exception = new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
            $this->exception = $exception;
            $this->logException($exception);

            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $this->renderException($exception);
        }
    }

    /**
     * Renders the exception.
     * @param \Exception $exception the exception to be rendered.
     */
    abstract protected function renderException($exception);

    /**
     * Logs the given exception.
     * @param \Exception $exception the exception to be logged
     */
    public function logException($exception)
    {
        $category = get_class($exception);
        if ($exception instanceof HttpException) {
            $category = 'Reaction\\Exceptions\\HttpException:' . $exception->statusCode;
        } elseif ($exception instanceof \ErrorException) {
            $category .= ':' . $exception->getSeverity();
        }
        $name = get_class($exception);
        $message = $exception->getMessage();
        $fileLine = '';
        if ($exception->getFile() && $exception->getLine()) {
            $fileLine = "\n" . $exception->getFile() . ':' . $exception->getLine();
        }
        $logMessage = $name . "\n" . $message . $fileLine;
        if (Reaction\Helpers\Console::streamSupportsAnsiColors(STDOUT)) {
            $logMessage = Reaction\Helpers\Console::ansiFormat($logMessage, [Reaction\Helpers\Console::FG_RED]);
        }
        Reaction::$app->logger->logRaw($logMessage);
    }

    /**
     * Removes all output echoed before calling this method.
     */
    public function clearOutput()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }

    /**
     * Handles exception thrown during exception processing in [[handleException()]].
     * @param \Exception|\Throwable $exception Exception that was thrown during main exception processing.
     * @param \Exception $previousException Main exception processed in [[handleException()]].
     */
    protected function handleFallbackExceptionMessage($exception, $previousException)
    {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string) $exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string) $previousException;
        if (Reaction::isDebug()) {
            if (Reaction::isConsoleApp()) {
                echo $msg . "\n";
            } else {
                echo '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Reaction::$app->charset) . '</pre>';
            }
        } else {
            echo 'An internal server error occurred.';
        }
        $msg .= "\n\$_SERVER (GLOBAL) = " . VarDumper::export($_SERVER);
        $msg .= "\n\$_SERVER (LOCAL) = " . VarDumper::export($this->app->reqHelper->getServerParams());
        error_log($msg);
    }

    /**
     * Converts an exception into a PHP error.
     *
     * This method can be used to convert exceptions inside of methods like `__toString()`
     * to PHP errors because exceptions cannot be thrown inside of them.
     * @param \Exception $exception the exception to convert to a PHP error.
     */
    public static function convertExceptionToError($exception)
    {
        trigger_error(static::convertExceptionToString($exception), E_USER_ERROR);
    }

    /**
     * Converts an exception into a simple string.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     */
    public static function convertExceptionToString($exception)
    {
        if ($exception instanceof UserException) {
            return "{$exception->getName()}: {$exception->getMessage()}";
        }

        if (Reaction::isDebug()) {
            return static::convertExceptionToVerboseString($exception);
        }

        return 'An internal server error occurred.';
    }

    /**
     * Converts an exception into a string that has verbose information about the exception and its trace.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     */
    public static function convertExceptionToVerboseString($exception)
    {
        if ($exception instanceof Exception) {
            $message = "Exception ({$exception->getName()})";
        } elseif ($exception instanceof ErrorException) {
            $message = (string)$exception->getName();
        } else {
            $message = 'Exception';
        }
        $message .= " '" . get_class($exception) . "' with message '{$exception->getMessage()}' \n\nin "
            . $exception->getFile() . ':' . $exception->getLine() . "\n\n"
            . "Stack trace:\n" . $exception->getTraceAsString();

        return $message;
    }

    /**
     * Manual loading ErrorException class file.
     * Load ErrorException manually here because autoloading them will not work
     * when error occurs while autoloading a class
     */
    protected static function loadErrorExceptionClass()
    {
        if (!class_exists('Reaction\\Exceptions\\ErrorException', false)) {
            require_once __DIR__ . '/../Exceptions/ErrorException.php';
        }
    }
}