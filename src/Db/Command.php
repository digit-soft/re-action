<?php

namespace Reaction\Db;

use Reaction\Base\Component;

/**
 * Class Command
 * @package Reaction\Db
 */
class Command extends Component implements CommandInterface
{
    /** @var ConnectionInterface */
    public $connection;
}