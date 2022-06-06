<?php
namespace kbtests;

use karmabunny\pdb\Cache\PdbStaticCache;
use karmabunny\pdb\Pdb;

/**
 *
 */
class CacheStaticTest extends BaseCacheCase
{

    public function setUp(): void
    {
        $this->pdb = Pdb::create([
            'cache' => PdbStaticCache::class,
        ]);

        parent::setUp();
    }

}
