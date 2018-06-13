<?php

namespace Reaction\Annotations;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("path", type = "string", required = true),
 *   @Attribute("method",  type = "array"),
 * })
 *
 * Some Annotation using a constructor
 */
class CtrlAction
{
    /** @var array Possible HTTP request methods */
    const METHODS = ["GET", "HEAD", "POST", "PUT", "DELETE", "CONNECT", "OPTIONS", "PATCH", "TRACE"];

    /** @var array Http method */
    public $method = ['GET'];

    /**
     * @Required
     * @var string Action path
     */
    public $path;

    /**
     * CtrlAction constructor.
     * @param array $values
     * @throws \Exception
     */
    public function __construct($values = [])
    {
        if (count($values) === 1 && isset($values['value'])) {
            $values = $values['value'];
        }
        if (is_string($values)) {
            $this->path = $values;
        } elseif (is_array($values)) {
            if (!empty($values['method'])) {
                $this->method = $this->normalizeMethod($values['method']);
            }
            if (!empty($values['path'])) {
                $this->path = $this->normalizePath($values['path']);
            }
        }

        if (empty($this->path)) {
            throw new \Exception("Property 'path' is required");
        }
    }

    /**
     * Normalize path
     * @param string $path
     * @return string|null
     */
    protected function normalizePath($path) {
        if (!isset($path)) {
            return null;
        }
        $path = trim((string)$path);
        if (($qPos = strpos($path, '?')) !== false) {
            $path = mb_substr($path, 0, $qPos);
        }
        if ($path !== "/") {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * Normalize method
     * @param array|string $method
     * @return array
     */
    protected function normalizeMethod($method)
    {
        $method = (array)$method;
        foreach ($method as &$methodRow) {
            $methodRow = strtoupper($methodRow);
        }
        if (in_array('ANY', $method)) {
            $method = static::METHODS;
        }
        return $method;
    }
}