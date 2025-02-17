<?php

namespace kbtests;

use karmabunny\pdb\Drivers\PdbMysql;
use karmabunny\pdb\Drivers\PdbPgsql;
use karmabunny\pdb\Drivers\PdbSqlite;
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


    public function drop()
    {
        // OK whoever wrote this stupid method (me)...
        // HOW DO I KNOW WHAT IS AND ISN'T PREFIXED??
        $tables = $this->pdb->listTables();

        foreach ($tables as $table) {
            $this->pdb->query("DROP TABLE IF EXISTS ~{$table}", [], 'null');
            $this->pdb->query("DROP TABLE IF EXISTS {$table}", [], 'null');
        }

        $tables = $this->pdb->listTables();
        $this->assertEmpty($tables, implode(',', $tables));
    }


    public function sync()
    {
        if (!$this->pdb instanceof PdbMysql) {
            $this->markTestSkipped('Skipping sync test for non-mysql driver');
        }

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

        $queries = '';
        foreach ($sync->getQueries() as $query) {
            $queries .= "\n" . $query->sql;
        }

        $this->assertEmpty($queries, $queries);
    }


    public function testSync()
    {
        $this->drop();
        $this->sync();
    }


    public function testTables()
    {
        $this->drop();
        $this->sync();

        $actual = $this->pdb->listTables();
        $this->assertNotEmpty($actual);

        $expected = array_keys($this->struct->tables);

        sort($actual);
        sort($expected);

        $this->assertEquals($expected, $actual);
    }


    public function testColumns()
    {
        $this->markTestSkipped('Casing for the types is wrong, should probably apply some normalisation.');

        $this->sync();

        $columns = $this->pdb->fieldList('clubs');
        $this->assertNotEmpty($columns);

        $table = $this->struct->tables['clubs'];
        $expected = $table->columns;
        $this->assertEquals($expected, $columns);
    }


    public function testTimezoneNow(): void
    {
        $now = $this->pdb->now();
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $now);

        $now = $this->pdb->now('Y-m-d');
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $now);

        $actual = $this->pdb->getTimezone();
        $expected = date_default_timezone_get();
        $this->assertEquals($expected, $actual->getName());
    }


    public function testTimezoneSystem(): void
    {
        date_default_timezone_set('UTC');
        $pdb = Pdb::create($this->pdb->config);
        $pdb->config->use_system_timezone = true;

        $tz = $pdb->getTimezone(true);
        $this->assertEquals('UTC', $tz->getName());

        $now = date('Y-m-d H:i:s');
        $this->assertEquals($now, $pdb->now());

        // Change the PHP timezone.
        date_default_timezone_set('Australia/Adelaide');
        $pdb = Pdb::create($this->pdb->config);
        $pdb->config->use_system_timezone = true;

        $tz = $pdb->getTimezone(true);
        $this->assertEquals('Australia/Adelaide', $tz->getName());

        $utc = gmdate('Y-m-d H:i:s');
        $this->assertNotEquals($utc, $pdb->now());

        $now = date('Y-m-d H:i:s');
        $this->assertEquals($now, $pdb->now());
    }


    public function testTimezoneDb(): void
    {
        // Let the DB decide.
        $pdb = Pdb::create($this->pdb->config);
        $pdb->config->use_system_timezone = false;

        if ($this->pdb instanceof PdbSqlite) {
            $tz = $pdb->getTimezone(true);
            $this->assertEquals('UTC', $tz->getName());
        }
        else {
            // Force it's hand a bit.
            if ($this->pdb instanceof PdbPgsql) {
                $pdb->query("SET timezone = 'America/New_York'", [], 'null');
            }
            else {
                $pdb->query('SET time_zone = ?', ['America/New_York'], 'null');
            }

            $tz = $pdb->getTimezone(true);
            $this->assertEquals('America/New_York', $tz->getName());

            date_default_timezone_set('Australia/Adelaide');
            $now = date('Y-m-d H:i:s');
            $this->assertNotEquals($now, $pdb->now());

            date_default_timezone_set('America/New_York');
            $now = date('Y-m-d H:i:s');
            $this->assertEquals($now, $pdb->now());
        }
    }

}
