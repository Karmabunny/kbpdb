<?php

namespace karmabunny\pdb\Models;

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbSchemaInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbSchema implements PdbSchemaInterface
{

    protected $pdb;


    public function __construct(Pdb $pdb)
    {
        $this->pdb = $pdb;
    }


    /** @inheritdoc */
    public function getTableNames(): array
    {
        return $this->pdb->getTableNames();
    }


    /** @inheritdoc */
    public function getTables(): array
    {
        return $this->pdb->tableList();
    }


    /** @inheritdoc */
    public function getViews(): array
    {
        // TODO Not so easy.
        return [];
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
