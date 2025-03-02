<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;

use karmabunny\kb\ConfigurableInit;
use karmabunny\kb\Json;
use karmabunny\rdb\Rdb;

/**
 * A cache backed by redis.
 *
 * The connection config is provided by {@see PdbConfig}.
 *
 * @package karmabunny\pdb
 */
class PdbRedisCache extends PdbCache implements ConfigurableInit
{

    /** @var Rdb */
    public $rdb;

    /** @var array */
    public $config = [];

    /** @var bool */
    protected $_init = true;


    /**
     * Create this cache.
     *
     * @param array $config array config for rdb {@see RdbConfig}
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->update($config);

        if ($config) {
            $this->init();
        }
    }


    /** @inheritdoc */
    public function update($config)
    {
        if (!is_array($config)) {
            $config = iterator_to_array($config);
        }

        $this->config = $config;
        $this->_init = true;
    }


    /**
     * @return void
     */
    public function init()
    {
        if (!$this->_init) return;
        $this->_init = false;
        $this->rdb = Rdb::create($this->config);
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
        return (bool) $this->rdb->exists($key);
    }


    /** @inheritdoc */
    public function get(string $key)
    {
        $value = $this->rdb->get($key);
        if (!$value) return $value;
        return Json::decode($value);
    }


    /** @inheritdoc */
    public function clear(?string $key = null)
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
