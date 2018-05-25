<?php

namespace Reaction\Console\Routes;

use Reaction\Exceptions\HttpException;
use Reaction\Helpers\Console;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\resolve;
use Reaction\RequestApplicationInterface;
use Reaction\Web\Response;
use Reaction\Web\ResponseBuilderInterface;

/**
 * Class Controller
 * @package Reaction\Console\Routes
 */
class Controller extends \Reaction\Routes\Controller
{

    /**
     * @var bool whether to enable ANSI color in the output.
     * If not set, ANSI color will only be enabled for terminals that support it.
     */
    public $color;

    /**
     * @var bool whether to run the command interactively.
     */
    public $interactive = true;

    /**
     * @var bool whether to display help information about current command.
     * @since 2.0.10
     */
    public $help;

    /**
     * @var array the options passed during execution.
     */
    protected $_passedOptions = [];

    /**
     * @inheritdoc
     */
    public function resolveError(RequestApplicationInterface $app, \Throwable $exception, $asPlainText = false)
    {
        return $this->resolveErrorAsPlainText($app, $exception);
    }

    /**
     * Resolve error as pain text
     * @param RequestApplicationInterface $app
     * @param \Throwable                  $exception
     * @return ResponseBuilderInterface
     */
    protected function resolveErrorAsPlainText(RequestApplicationInterface $app, \Throwable $exception)
    {
        $data = $this->getErrorData($exception);
        $body = $data['name'] . ' (' . $data['code'] . ')' .  "\n";
        $body .= $data['message'] !== "" ? $data['message'] . "\n" : '';
        $body .= $data['file'] !== "" ? $data['file'] : '';
        $body .= $data['line'] !== "" ? " #" . $data['line'] . "\n" : '';

        if (!$exception instanceof HttpException && !empty($data['trace'])) {
            $body .= $data['trace'] . "\n";
        }

        $app->response->setBody($body)
            ->setStatusCodeByException($exception)
            ->setFormat(Response::FORMAT_RAW);
        return $app->response;
    }


    /**
     * Returns a value indicating whether ANSI color is enabled.
     *
     * ANSI color is enabled only if [[color]] is set true or is not set
     * and the terminal supports ANSI color.
     *
     * @param resource $stream the stream to check.
     * @return bool Whether to enable ANSI style in output.
     */
    public function isColorEnabled($stream = \STDOUT)
    {
        return $this->color === null ? Console::streamSupportsAnsiColors($stream) : $this->color;
    }

    /**
     * Formats a string with ANSI codes.
     *
     * You may pass additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * echo $this->ansiFormat('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to be formatted
     * @return string
     */
    public function ansiFormat($string)
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return $string;
    }

    /**
     * Prints a string to STDOUT.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stdout('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public function stdout($string)
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return Console::stdout($string);
    }

    /**
     * Prompts the user for input and validates it.
     *
     * @param string $text prompt string
     * @param array $options the options to validate the input:
     *
     *  - required: whether it is required or not
     *  - default: default value if no input is inserted by the user
     *  - pattern: regular expression pattern to validate user input
     *  - validator: a callable function to validate input. The function must accept two parameters:
     *      - $input: the user input to validate
     *      - $error: the error value passed by reference if validation failed.
     *
     * An example of how to use the prompt method with a validator function.
     *
     * ```php
     * $code = $this->prompt('Enter 4-Chars-Pin', ['required' => true, 'validator' => function($input, &$error) {
     *     if (strlen($input) !== 4) {
     *         $error = 'The Pin must be exactly 4 chars!';
     *         return false;
     *     }
     *     return true;
     * }]);
     * ```
     *
     * @return ExtendedPromiseInterface with string the user input
     */
    public function prompt($text, $options = [])
    {
        if ($this->interactive) {
            return Console::prompt($text, $options);
        }

        $default = isset($options['default']) ? $options['default'] : '';
        return resolve($default);
    }

    /**
     * Asks user to confirm by typing y or n.
     *
     * A typical usage looks like the following:
     *
     * ```php
     * if ($this->confirm("Are you sure?")) {
     *     echo "user typed yes\n";
     * } else {
     *     echo "user typed no\n";
     * }
     * ```
     *
     * @param string $message to echo out before waiting for user input
     * @param bool $default this value is returned if no selection is made.
     * @return ExtendedPromiseInterface with bool whether user confirmed.
     * Will return true if [[interactive]] is false.
     */
    public function confirm($message, $default = false)
    {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        }

        return resolve(true);
    }

    /**
     * Gives the user an option to choose from. Giving '?' as an input will show
     * a list of options to choose from and their explanations.
     *
     * @param string $prompt the prompt message
     * @param array $options Key-value array of options to choose from
     *
     * @return ExtendedPromiseInterface with string An option character the user chose
     */
    public function select($prompt, $options = [])
    {
        return Console::select($prompt, $options);
    }

    /**
     * Returns the names of valid options for the action (id)
     * An option requires the existence of a public member variable whose
     * name is the option name.
     * Child classes may override this method to specify possible options.
     *
     * Note that the values setting via options are not available
     * until [[beforeAction()]] is being called.
     *
     * @param string $actionID the action id of the current request
     * @return string[] the names of the options valid for the action
     */
    public function options($actionID = null)
    {
        // $actionId might be used in subclasses to provide options specific to action id
        return ['color', 'interactive', 'help'];
    }

    /**
     * Returns option alias names.
     * Child classes may override this method to specify alias options.
     *
     * @return array the options alias names valid for the action
     * where the keys is alias name for option and value is option name.
     *
     * @see options()
     */
    public function optionAliases()
    {
        return [
            'h' => 'help',
        ];
    }

    /**
     * Returns properties corresponding to the options for the action id
     * Child classes may override this method to specify possible properties.
     *
     * @param string $actionID the action id of the current request
     * @return array properties corresponding to the options for the action
     */
    public function getOptionValues($actionID = null)
    {
        // $actionId might be used in subclasses to provide properties specific to action id
        $properties = [];
        foreach ($this->options($actionID) as $property) {
            $properties[$property] = $this->$property;
        }

        return $properties;
    }

    /**
     * Returns the names of valid options passed during execution.
     *
     * @return array the names of the options passed during execution
     */
    public function getPassedOptions()
    {
        return $this->_passedOptions;
    }

    /**
     * Returns the properties corresponding to the passed options.
     *
     * @return array the properties corresponding to the passed options
     */
    public function getPassedOptionValues()
    {
        $properties = [];
        foreach ($this->_passedOptions as $property) {
            $properties[$property] = $this->$property;
        }

        return $properties;
    }
}