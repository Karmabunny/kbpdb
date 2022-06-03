<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;


/**
 * Static cache.
 *
 * @package karmabunny\pdb
 */
class PdbStaticCache extends PdbCache
{

    /** @var array [ key => result ] */
    static $cache = [];

    /** @var array [ key => seconds ] */
    static $timeouts = [];


    /** @inheritdoc */
    public function store(string $key, $result, int $ttl)
    {
        static::$cache[$key] = $result;

        if ($ttl > 0) {
            $ts = microtime(true) + $ttl;
            static::$timeouts[$key] = $ts;
        }
    }


    /** @inheritdoc */
    public function has(string $key): bool
    {
        $this->_clean($key);
        return isset(static::$cache[$key]);
    }


    /** @inheritdoc */
    public function get(string $key)
    {
        $this->_clean($key);
        return static::$cache[$key] ?? null;
    }


    /** @inheritdoc */
    public function clear(string $key = null)
    {
        if ($key) {
            unset(static::$cache[$key]);
            unset(static::$timeouts[$key]);
        }
        else {
            static::$cache = [];
            static::$timeouts = [];
        }
    }


    /**
     *
     * @param string $key
     * @return void
     */
    protected function _clean(string $key)
    {
        $now = microtime(true);
        $ts = static::$timeouts[$key] ?? 0;

        if ($ts > $now) {
            unset(static::$cache[$key]);
            unset(static::$timeouts[$key]);
        }
    }
}
