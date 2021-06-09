<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbTable extends Collection
{
    /** @var string non-prefixed */
    public $name;

    /** @var PdbColumn[] name => PdbColumn */
    public $columns = [];

    /** @var PdbIndex[] */
    public $indexes = [];

    /** @var PdbForeignKey[] */
    public $foreign_keys = [];


    /**
     * A primary key can consist of one or many columns, apparently.
     *
     * @var string[]
     */
    public $primary_key = [];


    /**
     * A set of records to insert on table creation.
     *
     * These are not added on ALTER.
     *
     * @var string[][] list of [name => value (string)]
     */
    public $default_records = [];


    /**
     * Old names that this table used to have.
     *
     * Populated by PdbParser.
     *
     * @var string[]
     */
    public $previous_names = [];


    /**
     * Important but DB-specific properties of a table.
     *
     * @var string[]
     */
    public $attributes = [
        'engine' => 'InnoDB',
        'charset' => 'utf8',
        'collate' => 'utf8_unicode_ci',
    ];


    /**
     *
     * @param PdbColumn $column
     * @return static
     */
    public function addColumn(PdbColumn $column)
    {
        $this->columns[$column->name] = $column;
        return $this;
    }


    /**
     *
     * @param PdbTable[] $tables
     * @return string[]
     */
    public function check(array $tables): array
    {
        $errors = [];


        if (empty($this->columns)) {
            $errors[] = 'No columns defined';
            return $errors;
        }

        if (empty($this->primary_key)) {
            $errors[] = 'No primary key defined';
        }

        foreach ($this->columns as $column) {
            array_push($errors, ...$column->check($tables, $this));
        }

        foreach ($this->foreign_keys as $fk) {
            array_push($errors, ...$fk->check($tables, $this));
        }

        return $errors;
    }
}
