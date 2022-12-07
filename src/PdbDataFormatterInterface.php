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
interface PdbDataFormatterInterface
{

    /**
     *
     *
     * @param object $value
     * @return string
     */
    public function format(object $value): string;


    /**
     * Get the SQL snippet for an `INSERT/UPDATE SET` query.
     *
     * The column value will be a numeric placeholder like `?`.
     *
     * Such as: `"column" = ?`.
     *
     * Please note, although the value is properly escaped using prepared
     * statements - the column names are not. The Pdb object is provided for
     * this purpose {@see Pdb::quoteField}.
     *
     * @param Pdb $pdb
     * @param string $column
     * @return string
     */
    public function getBindingQuery(Pdb $pdb, string $column): string;
}
