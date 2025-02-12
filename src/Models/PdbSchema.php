<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;
use karmabunny\pdb\PdbSchemaInterface;

/**
 * A schema that comes from the database or a `db_struct.xml` file.
 *
 * @package karmabunny\pdb
 */
class PdbSchema extends Collection implements PdbSchemaInterface
{

    /** @var string */
    public $name;

    /** @var PdbTable[] name => PdbTable */
    public $tables = [];

    /** @var PdbView[] name => PdbView */
    public $views = [];


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


    /**
     *
     * @param PdbSchemaInterface $schema
     * @return string[]
     */
    public function check(PdbSchemaInterface $schema): array
    {
        $errors = [];

        $tables = $schema->getTables();

        foreach ($this->getTables() as $table) {
            $checks = $table->check($tables);
            if (!$checks) continue;
            array_push($errors, ...$checks);
        }

        return $errors;
    }

}
