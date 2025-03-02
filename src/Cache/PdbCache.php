<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;

/**
 *
 *
 * @package karmabunny\pdb
 */
abstract class PdbCache
{

    /**
     *
     * @param string $key
     * @param mixed $result
     * @param int $ttl
     * @return void
     */
    public abstract function store(string $key, $result, int $ttl);


    /**
     *
     * @param string $key
     * @return bool
     */
    public abstract function has(string $key): bool;


    /**
     *
     * @param string $key
     * @return mixed|null
     */
    public abstract function get(string $key);


    /**
     * Remove this cache item.
     *
     * @param string|null $key
     * @return void
     */
    public abstract function clear(?string $key = null);
}

