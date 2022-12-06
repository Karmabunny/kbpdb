<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

/**
 * This defines how values are formatted and bound to queries.
 *
 * When using Pdb insert/update objects implementing these can override
 * the behaviour of the binding SQL statements of the query using
 * the `getBinding()` method.
 *
 * The value is still bound using prepared statements. This interface also
 * lets one modify the value before binding using the `format()` method.
 *
 * @package karmabunny\pdb
 */
interface PdbDataModifierInterface
{

    /**
     * Format the value before binding to a query.
     *
     * Despite being labelled 'format' this can simply pass through through any
     * type of value. If the result is an object, even itself, it can be still
     * be formatted by the global formatters in {@see PdbConfig}.
     *
     * @return mixed
     */
    public function format();


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
    public function getBinding(Pdb $pdb, string $column): string;
}
