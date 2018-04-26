<?php

namespace Reaction\Web;

use Reaction\Base\BaseObject;

/**
 * Class RequestComponent
 * @package Reaction\Web\RequestComponents
 * @property AppRequestInterface $request
 */
class RequestComponent extends BaseObject implements RequestComponentInterface
{
    /** @var AppRequestInterface */
    public $request;
}