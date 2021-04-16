<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbTable extends Collection
{
    /** @var string */
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
     * @var array[] list of [name => value (string)]
     */
    public $default_records = [];


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
}
