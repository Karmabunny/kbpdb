<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMElement;
use DOMNode;
use karmabunny\kb\Collection;
use karmabunny\pdb\PdbStructWriterInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbTable extends Collection implements PdbStructWriterInterface
{

    const ATTRIBUTES = [
        'engine',
        'charset',
        'collate',
    ];

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


    /** @inheritdoc */
    public function toXml(DOMDocument $doc): DOMNode
    {
        $table = $doc->createElement('table');
        $table->setAttribute('name', $this->name);

        if ($this->previous_names) {
            $table->setAttribute('previous', implode(',', $this->previous_names));
        }

        foreach (self::ATTRIBUTES as $name) {
            if (!empty($this->attributes[$name])) {
                $table->setAttribute($name, $this->attributes[$name]);
            }
        }

        foreach ($this->columns as $column) {
            $node = $column->toXml($doc);
            $table->appendChild($node);
        }

        // These are actually indexes because our db_struct is built around the
        // mysql assumption that all FKs must have an index. Which is actually
        // totally fair and obvious for performance but troublesome otherwise.
        $fks = [];

        foreach ($this->foreign_keys as $fk) {
            $node = $fk->toXml($doc, true);
            $table->appendChild($node);

            $fks[$fk->from_column] = $node;
        }

        foreach ($this->indexes as $index) {
            // We've got a matching index + FK.
            if (
                count($index->columns) == 1
                and ($first = reset($index->columns))
                and ($fk = $fks[$first] ?? null)
            ) {
                // Modify the type if we can.
                if ($fk instanceof DOMElement and $index->type !== 'index') {
                    $fk->setAttribute('type', $index->type);
                }

                // Don't add another index.
                continue;
            }

            $node = $index->toXml($doc);
            $table->appendChild($node);
        }

        if ($this->default_records) {
            $node = $doc->createElement('default_records');
            $table->appendChild($node);

            foreach ($this->default_records as $record) {
                $node = $doc->createElement('record');

                foreach ($record as $name => $value) {
                    $node->setAttribute($name, $value);
                }

                $table->appendChild($node);
            }
        }

        return $table;
    }
}
