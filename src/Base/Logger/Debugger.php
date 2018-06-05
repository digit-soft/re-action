<?php

namespace Reaction\Base\Logger;

use Reaction\Helpers\Console;
use Reaction\Helpers\StringHelper;

/**
 * Class Debugger
 * @package Reaction\Base\Logger
 */
class Debugger
{
    /**
     * Print backtrace to console
     * @param bool $withArgs
     * @param int  $traceShift
     */
    public static function backTrace($withArgs = false, $traceShift = 0)
    {
        $trace = debug_backtrace();
        if ($traceShift > 0) {
            $trace = array_slice($trace, $traceShift);
        }
        $called = array_shift($trace);
        $exclude = [
            'React\Http\Io\MiddlewareRunner' => [],
            'React\Promise\Promise' => ['settle'],
            'Reaction\Promise\Promise' => ['settle'],
            'Rx\Observer\AutoDetachObserver' => [],
            'Rx\Observer\AbstractObserver' => [],
            'Rx\Observer\CallbackObserver' => [],
        ];
        $functions = [];
        $padLn = 12;
        $traceNum = 0;
        foreach ($trace as $row) {
            if (isset($row['class']) && isset($exclude[$row['class']])) {
                if (empty($exclude[$row['class']])) {
                    continue;
                } elseif (!empty($row['function']) && in_array($row['function'], $exclude[$row['class']])) {
                    continue;
                }
            }
            $function = $row['function'];
            $functionStr = isset($row['class'])
                ? $row['class'] . $row['type'] . $function
                : $function;
            $functionStr = Console::ansiFormat($functionStr, [Console::FG_GREEN, Console::BOLD]);
            if ($withArgs) {
                /** @var \Throwable[] $exceptionArgs */
                $exceptionArgs = [];
                list($argsSimple, $argsFull, $argsStrLength) = static::getArgumentsFromTrace($row, true);
                $argsFullExt = $argsStrLength > 100;
                $argsFullMaxLn = 0;
                $functionStr .= '(' . implode(', ', $argsSimple) . ')';
                if ($argsFullExt) {
                    foreach ($argsFull as $argName => $argValue) {
                        $argLn = mb_strlen($argName);
                        $argsFullMaxLn = $argsFullMaxLn < $argLn ? $argLn : $argsFullMaxLn;
                    }
                }
                $argsFullOld = $argsFull;
                $argsFull = [];
                foreach ($argsFullOld as $argName => $argValue) {
                    $argName = str_pad($argName, $argsFullMaxLn, ' ', STR_PAD_RIGHT);
                    $argsFull[] = Console::ansiFormat($argName, [Console::FG_GREEN]) . ' ' . $argValue;
                }
                $argsFullSeparator = $argsFullExt ? "\n" . str_repeat(' ', $padLn) : ", ";
                $functionStr .= !empty($argsFull) ? "\n" . str_repeat(' ', $padLn) . implode($argsFullSeparator, $argsFull) : '';
                if (!empty($exceptionArgs)) {
                    foreach ($exceptionArgs as $exceptionArg) {
                        $functionStr .= "\n" . str_pad('', $padLn, ' ', STR_PAD_LEFT)
                            . Console::ansiFormat($exceptionArg->getMessage(), [Console::FG_RED]);
                    }
                }
            } else {
                $functionStr .= '()';
            }
            $positionStr = '';
            if (isset($row['file']) && $row['file'] !== "") {
                $positionStr .= "\n" . str_pad('', $padLn, ' ', STR_PAD_RIGHT)
                    . Console::ansiFormat($row['file'], [Console::FG_PURPLE]);
            } else {
                $positionStr .= "\n" . str_pad('', $padLn, ' ', STR_PAD_RIGHT)
                    . Console::ansiFormat('{no path}', [Console::FG_PURPLE]);
            }
            if (isset($row['line']) && $row['line'] !== "") {
                $positionStr .= ' ' . Console::ansiFormat('#' . $row['line'], [Console::FG_CYAN]);
            }
            $functionStr .= $positionStr;
            $functionStr = str_pad('[' . $traceNum . '] ', $padLn - 1, '-', STR_PAD_RIGHT) . ' ' . $functionStr;
            $functionStr = str_pad($functionStr, $padLn, ' ', STR_PAD_RIGHT);
            $functions[] = $functionStr;
            $traceNum++;
        }
        $str = "\n" . implode("\n", $functions) . "\n";
        $str .= Console::ansiFormat("Called from " . $called['file'] . ' #' . $called['line'], [Console::FG_YELLOW]) . "\n";
        echo $str . "\n";
    }

    /**
     * Get trace row arguments
     * @param array $row
     * @param bool $explain
     * @return array
     */
    protected static function getArgumentsFromTrace($row, $explain = false)
    {
        $isClosure = strpos(strtolower($row['function']), '{closure}') !== false;
        $argsSimple = [];
        $argsFull = [];
        $argsStrLength = 0;
        if (!empty($row['args'])) {
            if (!$isClosure) {
                $funcRef = $row['class']
                    ? new \ReflectionMethod($row['class'], $row['function'])
                    : new \ReflectionFunction($row['function']);
                $funcParams = $funcRef->getParameters();
            } else {
                $funcParams = [];
            }
            foreach ($row['args'] as $argNum => $arg) {
                $argType = $argSimple = gettype($arg);
                $argName = '$...';
                if ($argType === 'object') {
                    $argSimple = get_class($arg);
                }
                if (isset($funcParams[$argNum])) {
                    $argName = '$' . $funcParams[$argNum]->name;
                    $argSimple .= Console::ansiFormat(' ' . $argName, [Console::FG_GREEN]);
                }
                $argSimple = Console::ansiFormat($argSimple, [Console::FG_CYAN]);
                $argsSimple[] = $argSimple;

                if (!$explain) {
                    continue;
                }

                $argIsException = false;
                if ($argType === 'object' && $arg instanceof \Throwable) {
                    $exceptionArgs[] = $argNum;
                    $argIsException = true;
                    $argValue = $arg->getMessage();
                } elseif ($argType === 'integer') {
                    $argValue = $arg;
                } elseif ($argType === 'string' && strlen($arg) < 30) {
                    $argValue = strlen($arg) < 30 ? $arg : StringHelper::truncate($arg, 30);
                    $argValue = "'" . $argValue . "'";
                } elseif ($argType === 'boolean') {
                    $argValue = !empty($arg) ? 'TRUE' : 'FALSE';
                } elseif ($argType === 'array') {
                    $argValue = 'array(' . count($arg) . ')';
                } elseif ($arg === null) {
                    $argValue = 'NULL';
                } else {
                    $argValue = $argType === 'object' ? get_class($arg) : $argType;
                }
                $argName = isset($argName) ? $argName : '$';
                $argsStrLength += mb_strlen($argName) + mb_strlen($argValue);
                $argFull = !$argIsException
                    ? Console::ansiFormat($argValue, [Console::FG_CYAN])
                    : Console::ansiFormat($argValue, [Console::FG_RED]);
                $argsFull[$argName] = $argFull;
            }
        }
        return [$argsSimple, $argsFull, $argsStrLength];
    }
}