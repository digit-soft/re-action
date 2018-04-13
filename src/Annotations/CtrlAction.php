<?php

namespace Reaction\Annotations;

/**
 * @Annotation
 * @Target({"METHOD","PROPERTY"})
 *
 * Some Annotation using a constructor
 */
class CtrlAction
{
    public $data;

    public function __construct($values = [])
    {
        \Reaction::$app->logger->warning($values);
    }
}