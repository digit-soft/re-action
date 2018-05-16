<?php

namespace Reaction\Db;

use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\resolve;

/**
 * ViewFinderTrait implements the method getViewNames for finding views in a database.
 */
trait ViewFinderTrait
{
    /**
     * @var array list of ALL view names in the database
     */
    private $_viewNames = [];

    /**
     * Returns all views names in the database.
     * @param string $schema the schema of the views. Defaults to empty string, meaning the current or default schema.
     * @return ExtendedPromiseInterface with array all views names in the database. The names have NO schema name prefix.
     */
    abstract protected function findViewNames($schema = '');

    /**
     * Returns all view names in the database.
     * @param string $schema the schema of the views. Defaults to empty string, meaning the current or default schema name.
     * If not empty, the returned view names will be prefixed with the schema name.
     * @param bool $refresh whether to fetch the latest available view names. If this is false,
     * view names fetched previously (if available) will be returned.
     * @return ExtendedPromiseInterface with string[] all view names in the database.
     */
    public function getViewNames($schema = '', $refresh = false)
    {
        if (!isset($this->_viewNames[$schema]) || $refresh) {
            return $this->findViewNames($schema)->then(
                function($names) use($schema) {
                    $this->_viewNames[$schema] = $names;
                    return $names;
                }
            );
        }

        return resolve($this->_viewNames[$schema]);
    }
}
