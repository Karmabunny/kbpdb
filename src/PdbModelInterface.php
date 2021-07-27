<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;


/**
 * The skeleton structure of a model.
 *
 * - Querying
 * - Saving (create/update)
 * - Deleting
 *
 * There are two implementations:
 * - {@see PdbModelTrait} (basic) only required an 'id' field.
 * - {@see PdbModel} (advanced) audit fields, soft deletes + uuids.
 *
 * @package karmabunny\pdb
 */
interface PdbModelInterface
{

    /**
     * The table name for this model, non-prefixed.
     *
     * @return string
     */
    public static function getTableName(): string;


    /**
     * Save this model.
     *
     * @return bool
     */
    public function save(): bool;


    /**
     * Delete this model.
     *
     * @param bool $soft 'Delete' without removing the record.
     * @return bool
     */
    public function delete($soft = true): bool;


    /**
     * Create a query for this model.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return PdbQuery
     */
    public static function find(array $conditions = []): PdbQuery;


    /**
     * Find one model.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return static
     */
    public static function findOne(array $conditions);


    /**
     * Find a list of models.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return static[]
     */
    public static function findAll(array $conditions = []);
}
