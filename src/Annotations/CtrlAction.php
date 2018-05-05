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
        if(is_string($values)) {
            $this->path = $values;
        } elseif (is_array($values)) {
            if (!empty($values['method'])) {
                $this->method = (array)$values['method'];
            }
            if (!empty($values['path'])) {
                $this->path = (string)$values['path'];
            }
        }

        $this->normalizePath();

        if(empty($this->path)) {
            throw new \Exception("Property 'path' is required");
        }
    }

    /**
     * Normalize path
     */
    protected function normalizePath() {
        if (!isset($this->path)) {
            return;
        }
        $path = trim($this->path);
        if (($qPos = strpos($path, '?')) !== false) {
            $path = mb_substr($path, $path);
        }
        $path = rtrim($path, '/');
        $this->path = $path;
        $this->extractParams();
    }
}