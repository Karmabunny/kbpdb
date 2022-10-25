<?php
namespace kbtests;

use karmabunny\pdb\Pdb;
use PHPUnit\Framework\TestCase;

/**
 *
 */
abstract class BaseCacheCase extends TestCase
{

    /** @var Pdb */
    public $pdb;

    public function setUp(): void
    {
        // TODO create test tables
    }


    public function testDefaultCaching(): void
    {
        // Using pdb.config.ttl settings
        // fetch row
        // drop drop
        // fetch within ttl (not-empty)
        // wait for ttl
        // fetch again (empty)
    }


    public function testThrowable(): void
    {
        // cache results should still 'throw' if empty
        // the cache should be empty
        // a subsequent query should perform another live check
    }


    public function testCrossConnection(): void
    {
        // one pdb instance should not share it's cache with another.
    }


    public function testCustomCaching(): void
    {
        // Custom TTL
        // Custom keys
    }
}
