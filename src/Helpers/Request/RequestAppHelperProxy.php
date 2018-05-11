<?php

namespace Reaction\Helpers\Request;

use Reaction\Base\RequestAppComponent;

/**
 * Class RequestHelperProxy
 * @package Reaction\Helpers\Request
 */
class RequestAppHelperProxy extends RequestAppComponent
{
    /** @var string Helper static class name */
    public $helperClass = '';

    /**
     * Proxy to Helper with added charset parameter at given position
     * @param string  $method
     * @param array   $arguments
     * @param integer $position
     * @return mixed
     */
    protected function proxyWithCharset($method, $arguments = [], $position = -1) {
        return $this->proxyWithAppProperty($method, $arguments, $position, 'charset');
    }

    /**
     * Proxy to Helper with added language parameter at given position
     * @param string  $method
     * @param array   $arguments
     * @param integer $position
     * @return mixed
     */
    protected function proxyWithLanguage($method, $arguments = [], $position = -1) {
        return $this->proxyWithAppProperty($method, $arguments, $position, 'language');
    }

    /**
     * Proxy to Helper with added parameter at given position
     * @param string $method
     * @param array  $arguments
     * @param int    $position
     * @param string $propertyName
     * @return mixed
     */
    private function proxyWithAppProperty($method, $arguments = [], $position = -1, $propertyName = 'charset')
    {
        $this->injectVariableToArguments($this->app->{$propertyName}, $arguments, $position);
        return $this->proxy($method, $arguments);
    }

    /**
     * Proxy to Helper with added RequestApplicationInterface parameter at given position
     * @param string $method
     * @param array  $arguments
     * @param int    $position
     * @return mixed
     */
    protected function proxyWithApp($method, $arguments = [], $position = -1)
    {
        $this->injectVariableToArguments($this->app, $arguments, $position);
        return $this->proxy($method, $arguments);
    }

    /**
     * @param string $method
     * @param array  $arguments
     * @return mixed
     */
    protected function proxy($method, $arguments = []) {
        return call_user_func_array([$this->helperClass, $method], $arguments);
    }

    /**
     * Inject variable to arguments array at given position
     * @param mixed $variable
     * @param array $arguments
     * @param int $position
     */
    protected function injectVariableToArguments($variable, &$arguments, $position = -1) {
        $argsCount = count($arguments);
        //Negative value handle (from end)
        if ($position < 0) {
            $position = $argsCount - $position;
        }
        if (!isset($arguments[$position]) && $position >= 0 && $position < $argsCount) {
           $arguments[$position] = $variable;
        }
    }
}