<?php

namespace Reaction\Base;

use Reaction\Exceptions\ErrorException;
use Reaction\Web\Response;

/**
 * Interface ErrorHandlerInterface
 * @package Reaction\Base
 */
interface ErrorHandlerInterface
{
    /**
     * Handles uncaught PHP exceptions.
     *
     * This method is implemented as a PHP exception handler.
     *
     * @param \Exception $exception the exception that is not caught
     * @return Response
     */
    public function handleException($exception);

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
    public function handleError($code, $message, $file, $line);

    /**
     * Handles fatal PHP errors.
     */
    public function handleFatalError();

    /**
     * Logs the given exception.
     * @param \Exception $exception the exception to be logged
     */
    public function logException($exception);

    /**
     * Converts an exception into a PHP error.
     *
     * This method can be used to convert exceptions inside of methods like `__toString()`
     * to PHP errors because exceptions cannot be thrown inside of them.
     * @param \Exception $exception the exception to convert to a PHP error.
     */
    public static function convertExceptionToError($exception);

    /**
     * Converts an exception into a simple string.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     */
    public static function convertExceptionToString($exception);

    /**
     * Converts an exception into a string that has verbose information about the exception and its trace.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     */
    public static function convertExceptionToVerboseString($exception);
}