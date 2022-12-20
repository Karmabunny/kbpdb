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
 * {@see PdbModelTrait} for implementations of find() and save().
 *
 * @package karmabunny\pdb
 */
interface PdbModelInterface
{

    /**
     * The connection used queries in this model.
     *
     * @return Pdb
     */
    public static function getConnection(): Pdb;


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
     * @return bool
     */
    public function delete(): bool;


    /**
     * Create a query for this model.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return PdbModelQuery
     */
    public static function find(array $conditions = []): PdbModelQuery;


    /**
     * Find one model.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return static
     */
    public static function findOne(array $conditions);


    /**
     * Find one model. Create a default one if not found.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return static
     */
    public static function findOrCreate(array $conditions);


    /**
     * Find a list of models.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return static[]
     */
    public static function findAll(array $conditions = []);
}
