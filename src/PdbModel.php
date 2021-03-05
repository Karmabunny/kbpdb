<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;


/**
 *
 * @package karmabunny\pdb
 */
interface PdbModel
{

    /**
     *
     * @return string
     */
    public static function getTableName(): string;


    /**
     *
     * @return bool
     */
    public function save(): bool;


    /**
     *
     * @return bool
     */
    public function delete($soft = true): bool;


    /**
     *
     * @param array $conditions
     * @return PdbQuery
     */
    public static function find(array $conditions = []): PdbQuery;

    /**
     * @param array $conditions
     * @return static
     */
    public static function findOne(array $conditions);


    /**
     * @param array $conditions
     * @return static[]
     */
    public static function findAll(array $conditions);
}
