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
 * the `getBindingQuery()` method.
 *
 * The value is still bound using prepared statements. This interface also
 * lets one modify the value before binding using the `getBindingValue()` method.
 *
 * Binders are applied before formatters.
 *
 * @see PdbDataFormatterInterface
 *
 * @package karmabunny\pdb
 */
interface PdbDataBinderInterface extends PdbDataInterface
{

    /**
     * Get the SQL snippet for an `UPDATE SET` query.
     *
     * The column value will be a numeric placeholder like `?`.
     *
     * Such as: `"column" = ?`.
     *
     * Please note, although the value is properly escaped using prepared
     * statements - the column names are not. The Pdb object is provided for
     * this purpose {@see Pdb::quoteField}.
     *
     * Also this is not used for `INSERT VALUES`. However `getBindingValue()`
     * is still used to process the value before binding.
     *
     * @param Pdb $pdb
     * @param string $column
     * @return string
     */
    public function getBindingQuery(Pdb $pdb, string $column): string;
}
