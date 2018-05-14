<?php

namespace Reaction\Db;

use Reaction\Base\RequestAppComponent;

/**
 * Class Connection
 * @package Reaction\Db
 */
class Connection extends RequestAppComponent implements ConnectionInterface
{
    public $host = 'localhost';
    public $post = '5432';
    public $user;
    public $password;
    public $database;

    /** @var string Database component name in static application */
    public $dbComponent = 'db';

    /** @var DatabaseInterface */
    public $db;
}