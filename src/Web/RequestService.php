<?php

namespace Reaction\Web;

use Reaction\DI\ServiceLocator;

/**
 * Class RequestService
 * @package Reaction\Web
 */
class RequestService extends ServiceLocator implements RequestServiceInterface
{
    /** @var AppRequestInterface Application request instance */
    public $request;

    /**
     * @inheritdoc
     */
    public function set($id, $definition)
    {
        //Extract definition from DI Definition
        if($definition instanceof \Reaction\DI\Definition) {
            $definition = $definition->dumpArrayDefinition();
        }

        if (is_string($definition)) {
            $config = ['class' => $definition];
        } elseif (\Reaction\Helpers\ArrayHelper::isIndexed($definition) && count($definition) === 2) {
            $config = $definition[0];
            $params = $definition[1];
        } else {
            $config = $definition;
        }

        //Inject request to newly created components
        if (is_array($config)) {
            $config['request'] = $this;
            $definition = isset($params) ? [$config, $params] : $config;
        }
        return parent::set($id, $definition);
    }
}