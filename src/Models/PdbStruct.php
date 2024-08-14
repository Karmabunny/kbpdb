<?php

namespace karmabunny\pdb\Models;

use karmabunny\pdb\PdbSchemaInterface;

/**
 * A schema that comes from a `db_struct.xml` file.
 *
 * @package karmabunny\pdb
 */
class PdbStruct implements PdbSchemaInterface
{

    /** @var string */
    public $name;

    /** @var PdbTables[] name => PdbTable */
    public $tables;

    /** @var PdbView[] name => PdbView */
    public $views;


    /** @inheritdoc */
    public function getTableNames(): array
    {
        return array_keys($this->tables);
    }


    /** @inheritdoc */
    public function getTables(): array
    {
        return $this->tables;
    }


    /** @inheritdoc */
    public function getViews(): array
    {
        return $this->views;
    }
}
