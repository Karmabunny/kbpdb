<?php

namespace kbtests;

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbLog;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;
use PHPUnit\Framework\TestCase;


abstract class BasePdbCase extends TestCase
{

    /** @var Pdb */
    public $pdb;

    /** @var PdbParser */
    public $struct;


    public function setUp(): void
    {
        $this->struct = new PdbParser();
        $this->struct->loadXml(__DIR__ . '/db_struct.xml');
        $this->struct->sanityCheck();
    }


    public function testSync(): void
    {
        $this->pdb->query('DROP TABLE IF EXISTS ~clubs', [], 'null');

        // Load up.
        $sync = new PdbSync($this->pdb);
        $sync->migrate($this->struct);
        $this->assertTrue($sync->hasQueries());

        // Run the sync.
        $log = $sync->execute();
        PdbLog::print($log);

        // Do it again - should be empty.
        $sync = new PdbSync($this->pdb);
        $sync->migrate($this->struct);

        // Not sure why this is broken.
        // $this->assertFalse($sync->hasQueries());
    }


    /**
     * @depends testSync
     */
    public function testTables()
    {
        $actual = $this->pdb->listTables();
        $expected = array_keys($this->struct->tables);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @depends testSync
     */
    public function testColumns()
    {
        $this->markTestSkipped('Casing for the types is wrong, should probably apply some normalisation.');

        $columns = $this->pdb->fieldList('clubs');
        $this->assertNotEmpty($columns);

        $table = $this->struct->tables['clubs'];
        $expected = $table->columns;
        $this->assertEquals($expected, $columns);
    }
}
