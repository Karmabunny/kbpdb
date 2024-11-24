<?php

namespace karmabunny\pdb;

use karmabunny\pdb\Models\PdbTable;
use karmabunny\pdb\Models\PdbView;

/**
 * An object that represents the structure of a database.
 *
 * @package karmabunny\pdb
 */
interface PdbSchemaInterface
{

    /**
     * List non-prefixed table names.
     *
     * @return string[]
     */
    public function getTableNames(): array;


    /**
     * A list of tables models.
     *
     * @return PdbTable[]
     */
    public function getTables(): array;


    /**
     * A list of views.
     *
     * @return PdbView[]
     */
    public function getViews(): array;

}
