<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

/**
 * A formatter is much like a `PdbDataBinderInterface` instead these are
 * registered globally rather than passed through as data objects.
 *
 * Formatters are applies after binders.
 *
 * This is a slightly different behaviour effect compared to the binder interface:
 *
 * 1. Binders can bind or format non-object values, whereas formatters cannot.
 * 2. Formatters apply to all queries, not just insert/update.
 *
 * As such, one might observe distinctly different but strikingly similar use-cases.
 *
 * @package karmabunny\pdb
 */
interface PdbDataFormatterInterface
{

    /**
     * Format a value before binding.
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
