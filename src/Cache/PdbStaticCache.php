<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;

use karmabunny\kb\Configurable;
use karmabunny\kb\UpdateTrait;

/**
 * Static cache.
 *
 * Lifetime of data in this cache is only that of the request.
 *
 * As such, be careful if you've built your queries with this assumption in
 * mind and then switch to a persistent/cross-request cache.
 *
 * By default TTL expiration is not enabled for this cache. It can be enabled
 * with the `enable_ttl` config but for 90% of user cases it's not necessary as
 * this cache doesn't live beyond the current request. The exception being
 * long-running CLI tasks.
 *
 * @package karmabunny\pdb
 */
class PdbStaticCache extends PdbCache implements Configurable
{
    use UpdateTrait;


    /** @var array [ key => result ] */
    protected static $cache = [];

    /** @var array [ key => seconds ] */
    protected static $timeouts = [];


    /**
     * Enable expiry of keys as advised by the TTL.
     * @var bool
     */
    public $enable_ttl = false;


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
     * Remove timeouts, if TTLs are enabled.
     *
     * @param string $key
     * @return void
     */
    protected function _clean(string $key)
    {
        if (!$this->enable_ttl) return;

        $now = microtime(true);
        $ts = static::$timeouts[$key] ?? 0;

        if ($ts > $now) {
            unset(static::$cache[$key]);
            unset(static::$timeouts[$key]);
        }
    }
}
