<?php

namespace Reaction\Console\Routes;

use Reaction\Console\Web\RequestHelper;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\HttpException;
use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\Console;
use Reaction\Helpers\Inflector;
use Reaction\Helpers\ReflectionHelper;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\resolve;
use Reaction\RequestApplicationInterface;
use Reaction\Web\Response;
use Reaction\Web\ResponseBuilderInterface;

/**
 * Class Controller
 * @package Reaction\Console\Routes
 * @property string|null $currentAction
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
     */
    public $help;

    /**
     * @var RequestApplicationInterface
     */
    public $app;

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
     * Get current action
     * @param RequestApplicationInterface|null $app
     * @return string|null
     */
    public function getCurrentAction(RequestApplicationInterface $app = null)
    {
        $app = $app !== null ? $app : $this->app;
        return $app !== null ? $app->getRoute()->getAction() : null;
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
        if ($exception instanceof \Reaction\Console\Exception) {
            $this->stdout($this->ansiFormat($data['message'], Console::FG_RED) . "\n");
            return null;
        } else {
            $body = $data['name'] . ' (' . $data['code'] . ')' . "\n";
            $body .= $data['message'] !== "" ? $data['message'] . "\n" : '';
            $body .= $data['file'] !== "" ? $data['file'] : '';
            $body .= $data['line'] !== "" ? " #" . $data['line'] . "\n" : '';
        }

        if (!$exception instanceof HttpException && !empty($data['trace'])) {
            $body .= $data['trace'] . "\n";
        }

        $app->response->setBody($body)
            ->setStatusCodeByException($exception)
            ->setFormat(Response::FORMAT_RAW);
        return $app->response;
    }

    /**
     * @inheritdoc
     */
    public function resolveAction(RequestApplicationInterface $app, string $action, ...$params)
    {
        /** @var RequestHelper $req */
        $req = $app->reqHelper;
        $paramsConsole = $req->getConsoleParams();
        $params = ArrayHelper::merge($params, $paramsConsole);
        array_unshift($params, $app);
        $this->processActionParams($action, $params);
        if ($this->help && $this->getUniqueId() !== 'help') {
            $routePath = $this->getUniqueId() . '/' . $action;
            return $app->resolveAction('help', 'GET', [$routePath]);
        }
        return parent::resolveAction($app, $action, ...$params);
    }

    /**
     * Get route group name, if empty no grouping
     * @return string
     */
    public function group()
    {
        $namespace = \Reaction::$app->router->getRelativeControllerNamespace(static::class);
        $namespace = substr($namespace, 0, -10);
        $namespaceArray = explode('\\', $namespace);
        array_walk($namespaceArray, function(&$value) {
            $value = Inflector::camel2id($value, '-');
        });
        return implode('/', $namespaceArray);
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
     * @param string $actionId the action id of the current request
     * @return string[] the names of the options valid for the action
     */
    public function options($actionId = null)
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

    /**
     * Returns one-line short summary describing this controller.
     *
     * You may override this method to return customized summary.
     * The default implementation returns first line from the PHPDoc comment.
     *
     * @return string
     */
    public function getHelpSummary()
    {
        return ReflectionHelper::getClassDocSummary($this);
    }

    /**
     * Returns help information for this controller.
     *
     * You may override this method to return customized help.
     * The default implementation returns help information retrieved from the PHPDoc comment.
     * @return string
     */
    public function getHelp()
    {
        return ReflectionHelper::getClassDocFull($this);
    }

    /**
     * Returns a one-line short summary describing the specified action.
     * @param string $actionId action to get summary for
     * @return string a one-line short summary describing the specified action.
     */
    public function getActionHelpSummary($actionId)
    {
        $method = static::getActionMethod($actionId);
        return ReflectionHelper::getMethodDocSummary($method, $this);
    }

    /**
     * Returns the detailed help information for the specified action.
     * @param string $actionId action to get help for
     * @return string the detailed help information for the specified action.
     */
    public function getActionHelp($actionId)
    {
        $method = static::getActionMethod($actionId);
        return ReflectionHelper::getMethodDocFull($method, $this);
    }

    /**
     * Returns the help information for the anonymous arguments for the action.
     *
     * The returned value should be an array. The keys are the argument names, and the values are
     * the corresponding help information. Each value must be an array of the following structure:
     *
     * - required: boolean, whether this argument is required.
     * - type: string, the PHP type of this argument.
     * - default: string, the default value of this argument
     * - comment: string, the comment of this argument
     *
     * The default implementation will return the help information extracted from the doc-comment of
     * the parameters corresponding to the action method.
     *
     * @param string $actionId
     * @return array the help information of the action arguments
     */
    public function getActionArgsHelp($actionId)
    {
        $methodName = static::getActionMethod($actionId);
        $method = ReflectionHelper::getMethodReflection($this, $methodName);
        $tags = ReflectionHelper::getMethodDocTags($method, $this);
        $params = isset($tags['param']) ? (array)$tags['param'] : [];

        $args = [];

        /** @var \ReflectionParameter $reflection */
        foreach ($method->getParameters() as $i => $reflection) {
            if ($reflection->getClass() !== null) {
                continue;
            }
            $name = $reflection->getName();
            $tag = isset($params[$i]) ? $params[$i] : '';
            if (preg_match('/^(\S+)\s+(\$\w+\s+)?(.*)/s', $tag, $matches)) {
                $type = $matches[1];
                $comment = $matches[3];
            } else {
                $type = null;
                $comment = $tag;
            }
            if ($reflection->isDefaultValueAvailable()) {
                $args[$name] = [
                    'required' => false,
                    'type' => $type,
                    'default' => $reflection->getDefaultValue(),
                    'comment' => $comment,
                ];
            } else {
                $args[$name] = [
                    'required' => true,
                    'type' => $type,
                    'default' => null,
                    'comment' => $comment,
                ];
            }
        }

        return $args;
    }

    /**
     * Returns the help information for the options for the action.
     *
     * The returned value should be an array. The keys are the option names, and the values are
     * the corresponding help information. Each value must be an array of the following structure:
     *
     * - type: string, the PHP type of this argument.
     * - default: string, the default value of this argument
     * - comment: string, the comment of this argument
     *
     * The default implementation will return the help information extracted from the doc-comment of
     * the properties corresponding to the action options.
     *
     * @param string $actionId
     * @return array the help information of the action options
     */
    public function getActionOptionsHelp($actionId)
    {
        $optionNames = $this->options($actionId);
        if (empty($optionNames)) {
            return [];
        }

        $class = ReflectionHelper::getClassReflection($this);
        $options = [];
        foreach ($class->getProperties() as $property) {
            $name = $property->getName();
            if (!in_array($name, $optionNames, true)) {
                continue;
            }
            $defaultValue = $property->getValue($this);
            $tags = ReflectionHelper::getPropertyDocTags($property);

            // Display camelCase options in kebab-case
            $name = Inflector::camel2id($name, '-', true);

            if (isset($tags['var']) || isset($tags['property'])) {
                $doc = isset($tags['var']) ? $tags['var'] : $tags['property'];
                if (is_array($doc)) {
                    $doc = reset($doc);
                }
                if (preg_match('/^(\S+)(.*)/s', $doc, $matches)) {
                    $type = $matches[1];
                    $comment = $matches[2];
                } else {
                    $type = null;
                    $comment = $doc;
                }
                $options[$name] = [
                    'type' => $type,
                    'default' => $defaultValue,
                    'comment' => $comment,
                ];
            } else {
                $options[$name] = [
                    'type' => null,
                    'default' => $defaultValue,
                    'comment' => '',
                ];
            }
        }

        return $options;
    }

    /**
     * Process action params array
     * @param string $actionId
     * @param array  $params
     * @throws Exception
     */
    protected function processActionParams(&$actionId, &$params = [])
    {
        $actionMethod = $this->normalizeActionName($actionId);
        if (!empty($params)) {
            // populate options here so that they are available in beforeAction().
            $options = $this->options($actionId);
            if (isset($params['_aliases'])) {
                $optionAliases = $this->optionAliases();
                foreach ($params['_aliases'] as $name => $value) {
                    if (array_key_exists($name, $optionAliases)) {
                        $params[$optionAliases[$name]] = $value;
                    } else {
                        throw new Exception('Unknown alias: -{name}', ['name' => $name]);
                    }
                }
                unset($params['_aliases']);
            }
            foreach ($params as $name => $value) {
                // Allow camelCase options to be entered in kebab-case
                if (!in_array($name, $options, true) && strpos($name, '-') !== false) {
                    $kebabName = $name;
                    $altName = lcfirst(Inflector::id2camel($kebabName));
                    if (in_array($altName, $options, true)) {
                        $name = $altName;
                    }
                }

                if (in_array($name, $options, true)) {
                    $default = $this->$name;
                    if (is_array($default)) {
                        $this->$name = preg_split('/\s*,\s*(?![^()]*\))/', $value);
                    } elseif ($default !== null) {
                        settype($value, gettype($default));
                        $this->$name = $value;
                    } else {
                        $this->$name = $value;
                    }
                    $this->_passedOptions[] = $name;
                    unset($params[$name]);
                    if (isset($kebabName)) {
                        unset($params[$kebabName]);
                    }
                } elseif (!is_int($name)) {
                    throw new Exception('Unknown option: --{name}', ['name' => $name]);
                }
            }
        }
        $argsData = ReflectionHelper::checkMethodArguments($params, $actionMethod, $this);
        if (!empty($argsData)) {
            $errorMessage = $this->ansiFormat("Controller action arguments error\n", Console::FG_RED, Console::BOLD);
            foreach ($argsData as $argName => $error) {
                $argError = $error === ReflectionHelper::ARG_REQUIRED_MISSING
                    ? "Missing required argument"
                    : "Argument type mismatch";
                $errorMessage .= "\t - "
                    . $this->ansiFormat($argName, Console::FG_GREEN, Console::BOLD)
                    . " - " . $this->ansiFormat($argError, Console::FG_RED)
                    . "\n";
            }
            throw new \Reaction\Console\Exception($errorMessage);
        }
    }
}