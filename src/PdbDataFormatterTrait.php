<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

/**
 * This is a default implementation for the `PdbDataFormatterInterface`.
 *
 * This is a 'fall-through' behaviour, so that the values simply do the
 * bare-minimum to implement a functional formatter.
 *
 * @package karmabunny\pdb
 */
trait PdbDataFormatterTrait
{

    /** @inheritdoc */
    public function format($value): string
    {
        return (string) $value;
    }


    /** @inheritdoc */
    public function getBindingQuery(Pdb $pdb, string $column): string
    {
        return $pdb->quoteField($column) . ' = ?';
    }
}
