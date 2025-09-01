<?php

namespace karmabunny\pdb\Drivers;

use Exception;
use karmabunny\pdb\Pdb;

class PdbNoDriver extends Pdb
{

    public function getPermissions(): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function getForeignKeys(string $table): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function getDependentKeys(string $table): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    /** @inheritdoc */
    public function getTableAttributes(string $table): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function extractEnumArr(string $table, string $column): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function getTableNames(string $filter = '*', bool $strip = true): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function tableExists(string $table): bool
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function fieldList(string $table): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    public function indexList(string $table): array
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }

}
