<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;
use karmabunny\pdb\Exceptions\RowMissingException;
use PDOStatement;

/**
 * Configuration for query return types.
 *
 * @package karmabunny\pdb
 */
interface PdbReturnInterface
{

    /**
     * Determine an appropriate TTL between the return config and pdb config.
     *
     * Return zero to disable caching.
     *
     * @return int
     */
    public function getCacheTtl(): int;


    /**
     * The uniqueness for this return config.
     *
     * A cache key is constructed from a few parts:
     * - the connection 'identity', typically a sha of the DSN
     * - the query string + parameters
     * - this return type
     *
     * This method is only called if the the cache is enabled via
     * the `getCacheTtl()` method.
     *
     * @return string
     */
    public function getCacheKey(): string;


    /**
     * Should this return config perform formatting?
     *
     * If this is false the result (from pdb::query and pdb::execute) will be
     * a raw PDOStatement.
     *
     * @return bool
     */
    public function hasFormatting(): bool;


    /**
     * Format the result set.
     *
     * @param PDOStatement $rs
     * @return string|int|null|array
     * @throws RowMissingException
     */
    public function format(PDOStatement $rs);


    /**
     * Format a result into class instance.
     *
     * @param array $result
     * @return object[]|object|null
     * @throws InvalidArgumentException
     */
    public function buildClass($result);

}
