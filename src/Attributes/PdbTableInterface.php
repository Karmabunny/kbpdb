<?php

namespace karmabunny\pdb\Attributes;

use karmabunny\pdb\Models\PdbTable;
use karmabunny\pdb\PdbModelInterface;
use ReflectionClass;

/**
 *
 * @property ReflectionClass<PdbModelInterface> $model
 * @package karmabunny\pdb
 */
interface PdbTableInterface
{

    /**
     *
     * @return PdbTable[]
     */
    public function getTables(): array;


    /**
     *
     * @return string[]
     */
    public function getErrors(): array;
}
