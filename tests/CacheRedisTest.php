<?php
namespace kbtests;

use karmabunny\kb\Env;
use karmabunny\pdb\Cache\PdbRedisCache;
use karmabunny\pdb\Pdb;
use karmabunny\rdb\Rdb;

/**
 *
 */
class CacheRedisTest extends BaseCacheCase
{

    public function setUp(): void
    {
        if (!class_exists(Rdb::class)) {
            $this->markTestSkipped('Redis not installed');
        }

        try {
            $cache = new PdbRedisCache([
                'host' => Env::isDocker() ? 'tcp://redis:6379' : 'localhost',
                'prefix' => 'pdb:',
            ]);
        }
        catch (Exception $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $this->pdb = Pdb::create([
            'cache' => $cache,
        ]);
    }
}
