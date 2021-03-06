<?php

namespace Reaction\Base;

use Reaction\DI\ServiceLocator;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Helpers\ArrayHelper;
use Reaction\I18n\RequestLanguageGetterInterface;
use Reaction\RequestApplicationInterface;

/**
 * Class RequestAppServiceLocator
 * @package Reaction\Base
 */
class RequestAppServiceLocator extends ServiceLocator implements RequestAppComponentInterface, RequestLanguageGetterInterface
{
    /** @var RequestApplicationInterface An request application instance reference */
    public $app;

    /**
     * Registers a component definition with this locator.
     * Overridden to inject application instance
     * @param string $id
     * @param mixed  $definition
     * @throws InvalidConfigException
     */
    public function set($id, $definition)
    {
        unset($this->_components[$id]);

        if ($definition === null) {
            unset($this->_definitions[$id]);
            return;
        }

        //Extract definition from DI Definition
        if ($definition instanceof \Reaction\DI\Definition) {
            $definition = $definition->dumpArrayDefinition();
        }

        if (is_string($definition)) {
            $config = ['class' => $definition];
        } elseif (ArrayHelper::isIndexed($definition) && count($definition) === 2) {
            $config = $definition[0];
            $params = $definition[1];
        } else {
            $config = $definition;
        }

        if (is_array($config)) {
            $config = ArrayHelper::merge(['app' => $this->app], $config);
            $definition = isset($params) ? [$config, $params] : $config;
        }

        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;
            } else {
                throw new InvalidConfigException("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    /**
     * Get language for current request
     * @return string
     */
    public function getRequestLanguage()
    {
        return $this->app->language;
    }
}