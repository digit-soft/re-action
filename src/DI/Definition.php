<?php

namespace Reaction\DI;

/**
 * Class Definition. DI container definition builder
 * Example:
 * Definition::of('App\TestClass')->withParams(['testString'])
 * Definition::create()->ofClass('App\TestClass')->withParams(['testString'])->withConfig(['db' => 'DbClass'])
 * @package Reaction\DI
 */
class Definition
{
    /** @var array Constructor parameters array */
    public $params = [];
    /** @var array Config array */
    public $config = [];
    /** @var null|string Class name */
    public $className;

    /**
     * Definition constructor.
     * @param string|null $className
     */
    public function __construct($className = null)
    {
        if (isset($className)) {
            $this->ofClass($className);
        }
    }

    /**
     * Set class name
     * @param string $className
     * @return Definition
     */
    public function ofClass($className)
    {
        $this->className = $className;
        $this->config['class'] = $className;
        return $this;
    }

    /**
     * Set config array
     * @param array $config
     * @return Definition
     */
    public function withConfig($config = [])
    {
        $this->config = $config;
        if (isset($config['class'])) {
            $this->className = $config['class'];
        } elseif (isset($this->className)) {
            $this->config['class'] = $this->className;
        }
        return $this;
    }

    /**
     * Set params array
     * @param array $params
     * @return Definition
     */
    public function withParams($params = [])
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Dump standard array definition
     * @return array
     */
    public function dumpArrayDefinition() {
        if (empty($this->config) || !isset($this->config['class'])) {
            return [];
        }
        return empty($this->params) ? $this->config : [
            $this->config,
            $this->params,
        ];
    }

    /**
     * Check that definition s valid (has class name specified)
     * @return bool
     */
    public function isValid() {
        return !empty($this->config) && isset($this->config['class']);
    }

    /**
     * Create new Definition with class name
     * @param string $className
     * @return Definition
     */
    public static function of($className = null)
    {
        return new static($className);
    }

    /**
     * Create new empty Definition
     * @return Definition
     */
    public static function create()
    {
        return new static();
    }
}