<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

/**
 * An interface for objects that are implicitly formatted before binding.
 *
 * @package karmabunny\pdb
 */
interface PdbDataInterface
{

    /**
     * Get the value to bind to a query.
     *
     * This provides an opportunity to format the value, or not.
     *
     * If the result is an object, even itself, it can be still
     * be formatted by the global formatters in {@see PdbConfig}.
     *
     * @return mixed
     */
    public function getBindingValue();
}
