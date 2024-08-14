<?php

namespace karmabunny\pdb\Drivers;

use Exception;
use karmabunny\pdb\Pdb;

class PdbNoDriver extends Pdb
{

    public function getPermissions()
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function getForeignKeys(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function getDependentKeys(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    /** @inheritdoc */
    public function getTableAttributes(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function extractEnumArr(string $table, string $column)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function getTableNames(string $prefix = '*')
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function tableExists(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function fieldList(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function indexList(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }

}
