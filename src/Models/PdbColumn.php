<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbColumn extends Collection
{
    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var bool */
    public $is_nullable = true;

    /** @var bool */
    public $is_primary = false;

    /**
     * This controls whether the field is auto incrementing.
     *
     * TODO The start number is determined by the parent table.
     *
     * @var bool
     */
    public $auto_increment = false;

    /** @var string|null */
    public $default = null;

    /** @var string[] */
    public $previous_names = [];

    /** @var string */
    public $extra = '';


    /**
     *
     * @param PdbTable[] $tables
     * @param PdbTable $table
     * @return string[]
     */
    public function check(array $tables, PdbTable $table): array
    {
        $errors = [];

        // If we have an ID column, do some additional checks.
        if ($this->name === 'id') {
            if (!preg_match('/^(BIG)?INT UNSIGNED$/i', $this->type)) {
                $errors[] = 'Bad type for "id" column, use INT UNSIGNED or BIGINT UNSIGNED';
            }

            // If the PK is autoinc, then it can't have one column.
            // TODO Just because ID is autoinc, we shouldn't also enforce it to
            // be a PK. Despite it almost always being the case.
            if ($this->auto_increment) {
                $primary = $table->primary_key[0] ?? null;

                if (
                    count($table->primary_key) !== 1 or
                    $primary !== 'id'
                ) {
                    $errors[] = 'Column is autoinc, but isn\'t only column in primary key';
                }
            }
        }

        return $errors;
    }


    /**
     * Gets the first key value pair of an iterable
     *
     * @param iterable $iter An array or Traversable
     * @return array|null An array of [key, value] or null if the iterable is empty
     * @example
     *          list ($key, $value) = Sprout::iterableFirst(['an' => 'array']);
     */
    public static function iterableFirst($iter)
    {
        foreach ($iter as $k => $v) {
            return [$k, $v];
        }

        return null;
    }


    /**
     * Get the PHP var type of column based on the column data type
     *
     * @return string
     */
    public function getPhpType()
    {
        $col_def_parts = preg_split('/\s+/', $this->type);
        $type = strtoupper(self::iterableFirst($col_def_parts)[1]);
        $type = explode('(', $type)[0];

        switch ($type) {

        case 'INT':
        case 'MEDIUMINT':
        case 'TINYINT':
        case 'BIGINT':
        case 'TIMESTAMP':
        case 'NUMERIC':
            $type = $this->is_nullable ? 'int|null' : 'int';
            break;

        case 'DECIMAL':
        case 'DOUBLE':
        case 'FLOAT':
        case 'REAL':
            $type = $this->is_nullable ? 'float|null' : 'int';
            break;

        case 'BOOL':
            $type = $this->is_nullable ? 'bool|null' : 'int';
            break;

        case 'DATETIME':
        case 'DATE':
        case 'YEAR':
        case 'VARBINARY':
        case 'VARCHAR':
        case 'BINARY':
        case 'TEXT':
        case 'BLOB':
        case 'JSON':
        case 'ENUM':
        case 'SET':

        default:
            $type = $this->is_nullable ? 'string|null' : 'string';
        }
        return $type;
    }
}
