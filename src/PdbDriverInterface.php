<?php

namespace karmabunny\pdb;

use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;

/**
 * Methods for a driver to implement.
 *
 * Most SQL things are common enough, but occasionally there a DBMS specific
 * syntaxes largely around schema, permissions, and non-standard features.
 *
 * @package karmabunny\pdb
 */
interface PdbDriverInterface
{

    /**
     * Get the permissions of the current connection.
     *
     * @return string[]
     */
    public function getPermissions(): array;


    /**
     * Gets all of the columns which have foreign key constraints in a table
     *
     * @param string $table non-prefixed
     * @return PdbForeignKey[]
     */
    public function getForeignKeys(string $table): array;


    /**
     * Gets all of the dependent foreign key columns (i.e. with the CASCADE delete rule) in other tables
     * which link to the id column of a specific table
     *
     * @param string $table non-prefixed
     * @return PdbForeignKey[]
     */
    public function getDependentKeys(string $table): array;


    /**
     * Get adapter specific table information.
     *
     * @param string $table non-prefixed
     * @return array [ key => value]
     */
    public function getTableAttributes(string $table): array;


    /**
     * Returns definition list from column of type ENUM
     *
     * @param string $table non-prefixed
     * @param string $column
     * @return string[]
     */
    public function extractEnumArr(string $table, string $column): array;


    /**
     * Fetches the current list of tables.
     *
     * @param string $filter
     *   - `''` - (empty) to return all tables
     *   - `'*'` - to return only tables with the default prefix
     *   - other - filter on this prefix
     * @param bool $strip remove the prefix from the table names
     * @return string[]
     */
    public function getTableNames(string $filter = '*', bool $strip = true): array;


    /**
     *
     * @param string $table non-prefixed
     * @return bool
     */
    public function tableExists(string $table): bool;


    /**
     *
     * @param string $table non-prefixed
     * @return PdbColumn[] [ name => PdbColumn ]
     */
    public function fieldList(string $table): array;


    /**
     *
     * @param string $table non-prefixed
     * @return PdbIndex[]
     */
    public function indexList(string $table): array;


    /**
     *
     * @param string $name
     * @param float $timeout in seconds
     * @return bool
     */
    public function createLock(string $name, float $timeout = 0): bool;


    /**
     *
     * @param string $name
     * @return bool
     */
    public function deleteLock(string $name): bool;

}
