<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;


/**
 * This implements basic querying interfaces for a model.
 *
 * @package karmabunny\pdb
 */
trait PdbModelTrait
{

    /**
     * The connection used queries in this model.
     *
     * @return Pdb
     */
    public abstract static function getConnection(): Pdb;


    /**
     * The table name for this model, non-prefixed.
     *
     * @return string
     */
    public abstract static function getTableName(): string;


    /**
     * Create a query for this model.
     *
     * @param array $conditions
     * @return PdbQuery
     */
    public static function find(array $conditions = []): PdbQuery
    {
        $pdb = static::getConnection();
        $table = static::getTableName();
        return (new PdbQuery($pdb))
            ->find($table, $conditions);
    }


    /**
     * Find one model.
     *
     * @param array $conditions
     * @return static
     */
    public static function findOne(array $conditions)
    {
        return self::find($conditions)
            ->as(static::class)
            ->one();
    }


    /**
     * Find a list of models.
     *
     * @param array $conditions
     * @return static[]
     */
    public static function findAll(array $conditions = [])
    {
        return self::find($conditions)
            ->as(static::class)
            ->all();
    }
}
