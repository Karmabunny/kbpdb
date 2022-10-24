<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;
use karmabunny\pdb\PdbHelpers;

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
     * Get the PHP var type of column based on the column data type
     *
     * @return string
     */
    public function getPhpType(): string
    {
        $type = PdbHelpers::convertDataType($this->type, true);

        if ($this->is_nullable) {
            $type .= '|null';
        }

        return $type;
    }
}
