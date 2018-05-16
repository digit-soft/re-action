<?php

namespace Reaction\Db;

/**
 * Interface ColumnSchemaInterface
 * @package Reaction\Db
 * @property string $name
 * @property bool   $allowNull
 * @property string $type
 * @property string $phpType
 * @property string $dbType
 * @property mixed  $defaultValue
 * @property array  $enumValues
 * @property int    $size
 * @property int    $precision
 * @property int    $scale
 * @property bool   $isPrimaryKey
 * @property bool   $autoIncrement
 * @property bool   $unsigned
 * @property string $comment
 */
interface ColumnSchemaInterface
{
    /**
     * Converts the input value according to [[phpType]] after retrieval from the database.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value
     */
    public function phpTypecast($value);

    /**
     * Converts the input value according to [[type]] and [[dbType]] for use in a db query.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value. This may also be an array containing the value as the first element
     * and the PDO type as the second element.
     */
    public function dbTypecast($value);
}