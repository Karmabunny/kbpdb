<?php
namespace kbtests;

use karmabunny\pdb\Cache\PdbDumbCache;
use karmabunny\pdb\Pdb;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class CacheDumbTest extends TestCase
{

    public function testNoCache(): void
    {
        $pdb = Pdb::create([
            'cache' => PdbDumbCache::class,
        ]);


    }
}
