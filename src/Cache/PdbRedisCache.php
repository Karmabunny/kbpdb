<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Cache;

use karmabunny\interfaces\ConfigurableInitInterface;
use karmabunny\kb\Json;
use karmabunny\rdb\Rdb;

/**
 * A cache backed by redis.
 *
 * The connection config is provided by {@see PdbConfig}.
 *
 * @package karmabunny\pdb
 */
class PdbRedisCache extends PdbCache implements ConfigurableInitInterface
{

    /** @var Rdb */
    public Rdb $rdb;

    /** @var array */
    public array $config = [];

    /** @var bool */
    protected bool $_init = true;


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
    public function store(string $key, mixed $result, int $ttl)
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
    public function get(string $key): mixed
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
