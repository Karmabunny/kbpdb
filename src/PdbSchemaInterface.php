<?php

namespace karmabunny\pdb;

use karmabunny\pdb\Models\PdbTable;
use karmabunny\pdb\Models\PdbView;

/**
 *
 * @package karmabunny\pdb
 */
interface PdbSchemaInterface
{

    /**
     *
     * @return string[]
     */
    public function getTableNames(): array;


    /**
     *
     * @return PdbTable[]
     */
    public function getTables(): array;


    /**
     *
     * @return PdbView[]
     */
    public function getViews(): array;

}
