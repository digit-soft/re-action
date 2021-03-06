<?php

namespace Reaction\Db;

/**
 * Interface DbConnectionGetterInterface
 * @package Reaction\Db
 */
interface DbConnectionGetterInterface
{
    /**
     * Get Database if applicable
     * @return DatabaseInterface|null
     */
    public function getDb();

    /**
     * Get Connection if applicable
     * @return ConnectionInterface|null
     */
    public function getConnection();
}