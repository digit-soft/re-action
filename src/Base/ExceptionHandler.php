<?php

namespace Reaction\Base;

use Reaction\BaseApplicationInterface;
use Reaction\Exceptions\HttpExceptionInterface;
use Reaction\Base\Logger\StdioLogger;
use Reaction\Web\Response;
use Reaction\Helpers\ArrayHelper;

/**
 * Class ExceptionHandler
 * @package app\base
 */
class ExceptionHandler extends BaseObject
{
    public $envType = BaseApplicationInterface::APP_ENV_PROD;

    /**
     * Throw error description to user (browser)
     * @param \Throwable $exception
     * @param bool       $asJson
     * @return Response
     */
    public function throwToUser($exception, $asJson = false) {
        $httpCode = method_exists($exception, 'getHttpCode') ? call_user_func([$exception, 'getHttpCode']) : 500;
        $full = $this->envType !== BaseApplicationInterface::APP_ENV_PROD;
        if($asJson) {
            $exceptionData = $this->getExceptionData($exception, $full, false);
        } else {
            $exceptionData = $this->renderException($exception, $full);
        }
        return $this->getResponse($httpCode, [], $exceptionData);
    }

    /**
     * Throw error description to Stdout
     * @param \Throwable $exception
     * @param bool       $full
     * @param bool       $asJson
     * @return Response
     */
    public function throwToStdout($exception, $full = true, $asJson = false) {
        $httpCode = method_exists($exception, 'getHttpCode') ? call_user_func([$exception, 'getHttpCode']) : 500;
        $logger = $this->getStdioLogger();
        $message = $this->getExceptionData($exception, $full, true);
        if($logger) {
            $logger->error($message, [], 1);
        } else {
            echo $message;
        }
        if($asJson) {
            $exceptionData = $this->getExceptionData($exception, $full, false);
        } else {
            $exceptionData = 'Error: ' . $exception->getMessage();
        }
        return $this->getResponse($httpCode, [], $exceptionData);
    }

    /**
     * Render exception view
     * @param \Throwable $exception
     * @param bool       $full
     * @return array|string
     */
    private function renderException($exception, $full = false) {
        $app = \Reaction::$app;
        try {
            $rendered = View::renderPhpStateless($this->getViewFileForException($exception), [
                'exceptionName' => $this->getExceptionName($exception),
                'exception' => $exception,
                'app' => $app,
                'rootPathLength' => strlen($app->getAlias('@root'))
            ]);
        } catch (\Throwable $e) {
            $rendered = $this->getExceptionData($exception, $full, true);
        }
        return $rendered;
    }

    /**
     * Get view file for exception depending on its code|httpStatusCode
     * @param \Throwable $exception
     * @return string
     */
    private function getViewFileForException($exception) {
        $basePath = \Reaction::$app->getAlias('@views/error');
        $tplName = 'general.php';
        if($exception instanceof HttpExceptionInterface) {
            $code = $exception->statusCode;
        } else {
            $code = $exception->getCode();
        }
        if(file_exists($basePath . '/' . $code . '.php')) $tplName = $code . '.php';
        return $basePath . '/' . $tplName;
    }

    /**
     * Get error response
     * @param int    $code
     * @param string $body
     * @param array $headers
     * @return Response
     */
    private function getResponse($code = 500, $headers = [], $body = null) {
        if(isset($body) && is_array($body)) {
            if(!isset($body['error'])) $body = ['error' => $body];
            $body = json_encode($body);
            $headers['Content-type'] = 'application/json';
        }
        return new Response($code, $headers, $body);
    }

    /**
     * Get exception data as string or array
     * @param \Throwable $exception
     * @param bool       $full
     * @param bool       $plainText
     * @return array|string
     */
    private function getExceptionData($exception, $full = false, $plainText = false) {
        if($plainText) {
            $text = $exception->getMessage() . "\n";
            if($full) {
                $text .= $exception->getFile() . " #" . $exception->getLine() . "\n";
                $text .= $exception->getTraceAsString() . "\n";
            }
            return $text;
        } else {
            $data = [
                'name' => $this->getExceptionName($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ];
            if($full) {
                $data = ArrayHelper::merge($data, [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'stacktrace' => $exception->getTrace(),
                ]);
            }
            return $data;
        }
    }

    /**
     * Get exception name (title)
     * @param \Throwable $exception
     * @return mixed
     */
    private function getExceptionName($exception) {
        return method_exists($exception, 'getName') ? call_user_func([$exception, 'getName']) : end(explode('\\', get_class($exception)));
    }

    /**
     * Get StdioLogger instance
     * @return StdioLogger|null
     */
    private function getStdioLogger() {
        try {
            /** @var StdioLogger $logger */
            $logger = \Reaction::$di->get('stdioLogger');
        } catch (\Throwable $exception) {
            $logger = null;
        }
        return $logger;
    }
}