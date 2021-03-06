<?php

namespace Reaction\Console\Web;

use Psr\Http\Message\ServerRequestInterface;
use Reaction;
use Reaction\Exceptions\Exception;
use Reaction\Exceptions\InvalidConfigException;

/**
 * Class RequestHelper
 * @package Reaction\Web
 * @property ServerRequestInterface $reactRequest
 * @property string                 $pathInfo
 */
class RequestHelper extends Reaction\Web\RequestHelper
{
    protected $_paramsRaw;
    protected $_params;
    protected $_route;

    /**
     * Resolves the path info part of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting slashes are both removed (ending slashes will be kept).
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    protected function resolvePathInfo()
    {
        $pathInfo = $this->getUrl();

        if (($pos = strpos($pathInfo, '?')) !== false) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }

        $pathInfo = urldecode($pathInfo);

        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }

        return (string)$pathInfo;
    }

    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        return 'GET';
    }

    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        if (!isset($this->_queryParams)) {
            $params = $this->getConsoleParams();
            $this->_queryParams = !empty($params) ? $params : [];
        }
        return $this->_queryParams;
    }

    /**
     * @inheritdoc
     */
    public function getQueryString()
    {
        $params = $this->getConsoleParams();
        return !empty($params) ? http_build_query($params) : '';
    }

    /**
     * @inheritdoc
     */
    protected function resolveRequestUri()
    {
        $path = $this->getConsolePathInfo();
        $queryString = $this->getQueryString();
        return $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    /**
     * Get command line parameters
     * @return array
     */
    public function getConsoleParams() {
        if (!isset($this->_params)) {
            $data = $this->getConsoleRouteAndParams();
            $this->_params = $data[1];
        }
        return $this->_params;
    }

    /**
     * Get console path info
     * @return string
     */
    protected function getConsolePathInfo() {
        if (!isset($this->_route)) {
            $data = $this->getConsoleRouteAndParams();
            $this->_route = (string)$data[0];
        }
        return $this->_route;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getConsoleRouteAndParams() {
        if (!isset($this->_route) || !isset($this->_params)) {
            list($this->_route, $this->_params) = $this->getConsoleRouteAndParamsRaw();
        }
        return [$this->_route, $this->_params];
    }

    /**
     * Get command line parameters
     * @return array
     * @internal
     */
    protected function getConsoleParamsRaw() {
        if ($this->_paramsRaw === null) {
            if (isset($_SERVER['argv'])) {
                $this->_paramsRaw = $_SERVER['argv'];
                array_shift($this->_paramsRaw);
            } else {
                $this->_paramsRaw = [];
            }
        }

        return $this->_paramsRaw;
    }

    /**
     * Resolves the current request into a route and the associated parameters.
     * @return array the first element is the route, and the second is the associated parameters.
     * @throws Exception
     */
    protected function getConsoleRouteAndParamsRaw()
    {
        $rawParams = $this->getConsoleParamsRaw();
        $endOfOptionsFound = false;
        if (isset($rawParams[0])) {
            $route = array_shift($rawParams);

            if ($route === '--') {
                $endOfOptionsFound = true;
                $route = array_shift($rawParams);
            }
        } else {
            $route = '';
        }

        $params = [];
        $prevOption = null;
        foreach ($rawParams as $param) {
            if ($endOfOptionsFound) {
                $params[] = $param;
            } elseif ($param === '--') {
                $endOfOptionsFound = true;
            } elseif (preg_match('/^--([\w-]+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                if (is_numeric(substr($name, 0, 1))) {
                    throw new Exception('Parameter "' . $name . '" is not valid');
                }
                $params[$name] = isset($matches[2]) ? $matches[2] : true;
                $prevOption = &$params[$name];
            } elseif (preg_match('/^-([\w-]+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                if (is_numeric($name)) {
                    $params[] = $param;
                } else {
                    $params['_aliases'][$name] = isset($matches[2]) ? $matches[2] : true;
                    $prevOption = &$params['_aliases'][$name];
                }
            } elseif ($prevOption === true) {
                // `--option value` syntax
                $prevOption = $param;
            } else {
                $params[] = $param;
            }
        }

        return [$route, $params];
    }
}