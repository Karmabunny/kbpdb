<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMElement;
use DOMNode;
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
        'charset' => 'utf8',
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
            $checks = $column->check($tables, $this);
            if (!$checks) continue;
            array_push($errors, ...$checks);
        }

        foreach ($this->foreign_keys as $fk) {
            $checks = $fk->check($tables, $this);
            if (!$checks) continue;
            array_push($errors, ...$checks);
        }

        return $errors;
    }


    /**
     * Calculate the row size in bytes.
     *
     * @return int
     */
    public function getRowSize(): int
    {
        $size = 0;

        foreach ($this->columns as $column) {
            $size += $column->getByteSize();
        }

        return $size;
    }


    public function toXML(DOMDocument $doc): DOMNode
    {
        $table = $doc->createElement('table');
        $table->setAttribute('name', $this->name);

        if ($this->previous_names) {
            $table->setAttribute('previous-names', implode(',', $this->previous_names));
        }

        foreach ($this->attributes as $name => $value) {
            $table->setAttribute($name, $value);
        }

        foreach ($this->columns as $column) {
            $node = $column->toXML($doc);
            $table->appendChild($node);
        }

        if ($this->primary_key) {
            $primary = $doc->createElement('primary');

            foreach ($this->primary_key as $key) {
                $node = $doc->createElement('col');
                $node->setAttribute('name', $key);
                $primary->appendChild($node);
            }

            $table->appendChild($primary);
        }

        foreach ($this->indexes as $index) {
            // The index is created with the FK instead.
            if ($index->has_fk) {
                continue;
            }

            $node = $index->toXML($doc);
            $table->appendChild($node);
        }

        foreach ($this->foreign_keys as $fk) {
            $node = $fk->toXML($doc);
            $table->appendChild($node);
        }

        return $table;
    }
}
