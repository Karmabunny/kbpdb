<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;


/**
 * Does nothing.
 *
 * @package karmabunny\pdb
 */
class PdbDumbCache extends PdbCache
{


    /** @inheritdoc */
    public function store(string $key, $result, int $ttl)
    {
    }


    /** @inheritdoc */
    public function has(string $key): bool
    {
        return false;
    }


    /** @inheritdoc */
    public function get(string $key)
    {
        return null;
    }


    /** @inheritdoc */
    public function clear(string $key = null)
    {
    }

}
