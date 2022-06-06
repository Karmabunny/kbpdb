<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;

use karmabunny\kb\Json;
use karmabunny\rdb\Rdb;

/**
 * A cache backed by redis.
 *
 * The connection config is provided by {@see PdbConfig}.
 *
 * @package karmabunny\pdb
 */
class PdbRedisCache extends PdbCache
{

    /** @var Rdb */
    public $rdb;


    /**
     * @param array $config
     * @return void
     */
    public function __construct($config)
    {
        $this->rdb = Rdb::create($config);
    }


    /** @inheritdoc */
    public function store(string $key, $result, int $ttl)
    {
        $json = Json::encode($result);
        $this->rdb->set($key, $json, $ttl * 1000);
    }


    /** @inheritdoc */
    public function has(string $key): bool
    {
        return $this->rdb->exists($key);
    }


    /** @inheritdoc */
    public function get(string $key)
    {
        $value = $this->rdb->get($key);
        if (!$value) return $value;
        return Json::decode($value);
    }


    /** @inheritdoc */
    public function clear(string $key = null)
    {
        if ($key) {
            $this->rdb->del($key);
        }
        else {
            $keys = $this->rdb->keys('*');
            $this->rdb->del($keys);
        }
    }

}
