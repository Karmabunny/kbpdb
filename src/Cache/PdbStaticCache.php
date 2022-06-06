<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;


/**
 * Static cache.
 *
 * Lifetime of data in this cache is only that of the request.
 *
 * As such, be careful if you've built your queries with this assumption in
 * mind and then switch to a persistent/cross-request cache.
 *
 * @package karmabunny\pdb
 */
class PdbStaticCache extends PdbCache
{

    /** @var array [ key => result ] */
    protected static $cache = [];

    /** @var array [ key => seconds ] */
    protected static $timeouts = [];


    /**
     * Enable expiry of keys as advised by the TTL.
     * @var bool
     */
    public $enable_ttl = false;


    /**
     * Config:
     * - enable_ttl: false
     *
     * This is a static cache. As such, it's lifetime is only that of the request.
     * So for 90% of user-cases a TTL expiry is not useful.
     *
     * Perhaps for CLI contexts.
     *
     * @param array $config
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->enable_ttl = $config['enable_ttl'] ?? false;
    }


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
