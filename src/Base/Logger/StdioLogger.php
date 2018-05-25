<?php

namespace Reaction\Base\Logger;

use Reaction\Helpers\ArrayHelper;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use React\EventLoop\LoopInterface;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;
use Reaction\Exceptions\InvalidArgumentException;

/**
 * Class StdioLogger
 * @package Reaction\Base\Logger
 */
class StdioLogger extends AbstractLogger implements LoggerInterface
{
    const LOG_LEVEL_RAW = 'raw';

    /**
     * Logging levels PSR-3 LogLevel enum.
     */
    const LOG_LEVELS = [
        self::LOG_LEVEL_RAW => 'RAW',
        LogLevel::DEBUG     => 'DEBUG',
        LogLevel::INFO      => 'INFO',
        LogLevel::NOTICE    => 'NOTICE',
        LogLevel::WARNING   => 'WARNING',
        LogLevel::ERROR     => 'ERROR',
        LogLevel::CRITICAL  => 'CRITICAL',
        LogLevel::ALERT     => 'ALERT',
        LogLevel::EMERGENCY => 'EMERGENCY',
    ];

    const NEW_LINE = PHP_EOL;

    const STYLE_OFF = 0;

    const FG_WHITE = 30;
    const FG_RED = 31;
    const FG_GREEN = 32;
    const FG_YELLOW = 33;
    const FG_BLUE = 34;
    const FG_MAGENTA = 35;
    const FG_CYAN = 36;
    const FG_BLACK = 37;

    const BG_RED = 41;
    const BG_GREEN = 42;
    const BG_YELLOW = 43;
    const BG_BLUE = 44;
    const BG_MAGENTA = 45;
    const BG_CYAN = 46;
    const BG_WHITE = 47;

    const BOLD = 1;
    const ITALIC = 3;
    const UNDERLINE = 4;

    public $withLineNum = false;

    protected static $LOG_COLORS = [
        LogLevel::ERROR => [ self::FG_RED ],
        LogLevel::ALERT => [ self::FG_WHITE, self::BG_RED ],
        LogLevel::INFO => [ self::FG_BLUE, self::ITALIC ],
        LogLevel::NOTICE => [ self::FG_BLUE, self::ITALIC ],
        LogLevel::CRITICAL => [ self::FG_WHITE, self::BG_RED, self::BOLD ],
        LogLevel::EMERGENCY => [ self::FG_WHITE, self::BG_RED, self::BOLD ],
        LogLevel::WARNING => [ self::FG_YELLOW, self::BOLD ],
    ];

    /** @var WritableStreamInterface Writable stream interface */
    private $stdio;

    /** @var bool */
    private $hideLevel = false;
    /** @var bool */
    private $newLine = false;
    /** @var LoopInterface  */
    private $loop;

    private $profilerData = [];

    /**
     * @param WritableStreamInterface $stream
     * @param LoopInterface           $loop
     *
     * @internal
     */
    public function __construct(WritableStreamInterface $stream, LoopInterface $loop)
    {
        $this->stdio = $stream;
        $this->loop = $loop;
    }

    /**
     * @param LoopInterface $loop
     * @return StdioLogger
     */
    public static function create(LoopInterface $loop): StdioLogger
    {
        return new self(new WritableResourceStream(STDOUT, $loop), $loop);
    }

    /**
     * Hide labels
     * @param bool $hideLevel
     * @return StdioLogger
     */
    public function withHideLevel(bool $hideLevel): StdioLogger
    {
        $clone = clone $this;
        $clone->hideLevel = $hideLevel;

        return $clone;
    }

    /**
     * Insert new line at the end
     * @param bool $newLine
     * @return StdioLogger
     */
    public function withNewLine(bool $newLine): StdioLogger
    {
        $clone = clone $this;
        $clone->newLine = $newLine;

        return $clone;
    }

    /**
     * System is unusable.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     * @return void
     */
    public function emergency($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::EMERGENCY, $message, $context, $traceShift);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function alert($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::ALERT, $message, $context, $traceShift);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function critical($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::CRITICAL, $message, $context, $traceShift);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function error($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::ERROR, $message, $context, $traceShift);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function warning($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::WARNING, $message, $context, $traceShift);
    }

    /**
     * Normal but significant events.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function notice($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::NOTICE, $message, $context, $traceShift);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function info($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::INFO, $message, $context, $traceShift);
    }

    /**
     * Detailed debug information.
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     *
     * @return void
     */
    public function debug($message, array $context = array(), $traceShift = 0)
    {
        $this->log(LogLevel::DEBUG, $message, $context, $traceShift);
    }

    /**
     * Print raw message
     *
     * @param string|mixed $message
     * @param array        $context
     * @param int          $traceShift
     * @return void
     */
    public function logRaw($message, array $context = array(), $traceShift = 0)
    {
        $this->log(static::LOG_LEVEL_RAW, $message, $context, $traceShift);
    }

    /**
     * Profiler
     * @param string $message
     * @param string $endId
     * @param int    $traceShift
     * @return null|string
     */
    public function profile($message = null, $endId = null, $traceShift = 0) {
        if(!isset($endId)) {
            $timeStart = microtime(true);
            $endId = ceil($timeStart * 100000) . mt_rand(11111, 99999);
            $this->profilerData[$endId] = [
                'message' => $message,
                'start' => $timeStart,
                'shift' => $traceShift,
            ];
            return $endId;
        } else {
            $data = ArrayHelper::remove($this->profilerData, $endId, null);
            if (!isset($data)) {
                return null;
            }
            $traceShift = isset($traceShift) ? $traceShift : (int)$data['shift'];
            $message = isset($message) ? $message : (string)$data['message'];
            if ($message === "") {
                $message = '{EMPTY MESSAGE}';
            }
            $timeSpent = (microtime(true) - $data['start']) * 1000;
            $message .= "\nTime: {_timeSpent} ms. Memory usage: {_memoryUsage} MB";
            $memoryUsage = memory_get_usage() / (1024 * 1024);
            $this->debug($message, [
                '_timeSpent' => sprintf('%.2f', $timeSpent),
                '_memoryUsage' => sprintf('%.2f', $memoryUsage),
            ], $traceShift);
        }
        return null;
    }

    /**
     * Profiler shortcut method for end
     * @param string $message
     * @param string $endId
     * @param int    $traceShift
     */
    public function profileEnd($endId, $message = null, $traceShift = null)
    {
        if (!isset($endId)) {
            return;
        }
        $this->profile($message, $endId, $traceShift);
    }

    /**
     * Log message to STDOUT
     * @param string $level
     * @param string $message
     * @param array  $context
     * @param int    $traceShift
     */
    public function log($level, $message, array $context = [], $traceShift = 0)
    {
        $this->checkCorrectLogLevel($level);
        $message = $this->convertMessageToString($message);
        $message = $this->processPlaceHolders($message, $context);
        if(strlen($message) > 0 && mb_substr($message, -1) !== "\n" && !$this->newLine) {
            $message .= self::NEW_LINE;
        }
        if ($this->hideLevel === false) {
            $logColors = isset(static::$LOG_COLORS[$level]) ? static::$LOG_COLORS[$level] : [];
            if ($level !== static::LOG_LEVEL_RAW) {
                $message = str_pad('[' . $level . ']', 10, ' ') . $message;
            }
            if (!empty($logColors)) {
                $message = $this->colorizeText($message, $logColors);
            }
        }
        if ($this->newLine === true) {
            $message .= self::NEW_LINE;
        }
        //Add script and line number
        if($this->withLineNum) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $lineNum = $this->getCalleeData($trace, $traceShift + 1);
            $unixTime = microtime(true);
            $micro = substr(sprintf("%06d",($unixTime - floor($unixTime)) * 1000000), 0, 3);
            $time = date('H:i:s') . '.' . $micro;
            $message .= $this->colorizeText('^^^ ' . $time . ' - ' . $lineNum, static::FG_BLACK) . self::NEW_LINE;
        }
        $message = $this->format($message, $context);
        $this->stdio->write($message);
    }

    /**
     * Convert message to string
     * @param mixed $message
     * @return mixed|string
     */
    protected function convertMessageToString($message) {
        $messageStr = $message;
        if ($message === null) {
            $messageStr = 'NULL';
        } elseif ($message instanceof \Throwable) {
            $messageStr = $this->convertErrorToString($message);
        } elseif (is_bool($message)) {
            $messageStr = $message ? 'TRUE' : 'FALSE';
        } elseif(!is_scalar($message)) {
            $messageStr = print_r($message, true);
        }
        return $messageStr;
    }

    /**
     * Convert exception to string
     * @param \Throwable $e
     * @param bool       $withTrace
     * @return string
     */
    protected function convertErrorToString(\Throwable $e, $withTrace = true) {
        $message = [
            $e->getMessage(),
            !empty($e->getFile()) ? $e->getFile()  . ' #' . $e->getLine() : "",
            $e->getTraceAsString(),
        ];
        if ($withTrace) {
            $message[] = $e->getTraceAsString();
            if ($e->getPrevious() !== null) {
                $message[] = $this->convertErrorToString($e->getPrevious(), false);
            }
        }
        return implode("\n", $message);
    }

    /**
     * Colorize text for console
     * @param string $text
     * @param mixed  ...$colors
     * @return bool|string
     */
    protected function colorizeText($text, ...$colors) {
        if (is_array($colors[0])) {
            $colors = $colors[0];
        }
        for ($i = 0; $i < count($colors); $i++) {
            $text = $this->_colorizeTextInternal($text, $colors[$i]);
        }
        return $text;
    }

    /**
     * @param string $text
     * @param string $color
     * @return bool|string
     */
    protected function _colorizeTextInternal($text, $color) {
        $off = static::STYLE_OFF;
        $newLineEnd = substr($text, -1) === "\n";
        if($newLineEnd) {
            $text = substr($text, 0, -1);
        }
        $text = "\033[" . $color . "m" . $text . "\033[" . $off. "m";
        if($newLineEnd) {
            $text .= "\n";
        }
        return $text;
    }

    /**
     * Get callee data from backtrace array
     * @param array $trace
     * @param int $pos
     * @return string
     */
    private function getCalleeData($trace, $pos = 0) {
        $traceRow = isset($trace[$pos]) ? $trace[$pos] : end($trace);
        $str = $traceRow['file'] . " #" . $traceRow['line'];
        return $str;
    }

    /**
     * Format message
     * @param string $message
     * @param array  $context
     * @return string
     */
    private function format(string $message, array $context): string
    {
        if (false !== strpos($message, '{') && !empty($context)) {
            $replacements = array();
            foreach ($context as $key => $val) {
                if (null === $val || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
                    $replacements["{{$key}}"] = $val;
                } elseif ($val instanceof \DateTimeInterface) {
                    $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
                } elseif (\is_object($val)) {
                    $replacements["{{$key}}"] = '[object '.\get_class($val).']';
                } else {
                    $replacements["{{$key}}"] = '['.\gettype($val).']';
                }
            }

            $message = strtr($message, $replacements);
        }

        return $message;
    }


    /**
     * Replace placeholders in text
     * @param  string $message
     * @param  array  $context
     * @return string
     */
    protected function processPlaceHolders(string $message, array $context): string
    {
        if (false === strpos($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{'.$key.'}'] = $this->formatValue($value);
        }

        return strtr($message, $replacements);
    }

    /**
     * @param string $level
     * @return bool
     */
    private function checkCorrectLogLevel(string $level): bool
    {
        $level = strtolower($level);
        $levels = static::LOG_LEVELS;
        if (!isset($levels[$level])) {
            throw new InvalidArgumentException(
                'Level "' . $level . '" is not defined, use one of: '.implode(', ', array_keys(static::LOG_LEVELS))
            );
        }

        return true;
    }

    /**
     * Format mixed value as a String
     * @param mixed $value
     * @return string
     */
    protected function formatValue($value): string
    {
        if (is_null($value) || is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string)$value;
        }

        if (is_object($value)) {
            return '[object '.get_class($value).']';
        }

        return '['.gettype($value).']';
    }
}