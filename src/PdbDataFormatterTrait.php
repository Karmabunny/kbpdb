<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

/**
 *
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
