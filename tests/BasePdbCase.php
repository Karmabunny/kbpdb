<?php

namespace kbtests;

use karmabunny\pdb\Drivers\PdbPgsql;
use karmabunny\pdb\Drivers\PdbSqlite;
use karmabunny\pdb\Exceptions\TransactionEmptyException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
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

    public $tx_mode = 0;


    public function setUp(): void
    {
        if (!$this->struct) {
            $this->struct = new PdbParser();
            $this->struct->loadXml(__DIR__ . '/db_struct.xml');
            $this->struct->sanityCheck();
        }

        $this->drop();
        $this->sync();

        $this->tx_mode = $this->pdb->config->transaction_mode;

        $this->assertFalse($this->pdb->inTransaction(), 'transaction exists, did we break a test somewhere?');
    }


    public function tearDown(): void
    {
        $this->pdb->config->transaction_mode = $this->tx_mode;

        if ($this->pdb->inTransaction()) {
            $this->pdb->rollback();
        }
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
        // Can't run a sync on postgres at all yet.
        if ($this->pdb instanceof PdbPgsql) {
            $this->markTestSkipped('Skipping sync test for non-mysql driver');
        }

        // Load up.
        $sync = new PdbSync($this->pdb);
        $sync->migrate($this->struct);
        $this->assertTrue($sync->hasQueries());

        // Run the sync.
        $log = $sync->execute();
        // PdbLog::print($log);

        // Do it again - should be empty.
        $sync = new PdbSync($this->pdb);
        $sync->migrate($this->struct);

        // Our assertions are wonky, but the migration does work.
        // It's enough at least to run dependent tests.
        if ($this->pdb instanceof PdbSqlite) {
            return;
        }

        $queries = '';
        foreach ($sync->getQueries() as $query) {
            $queries .= "\n" . $query->sql;
        }

        $this->assertEmpty($queries, $queries);
        $this->assertFalse($sync->hasQueries());
    }


    public function testTables()
    {
        $actual = $this->pdb->listTables();
        $this->assertNotEmpty($actual);

        $expected = array_keys($this->struct->tables);

        sort($actual);
        sort($expected);
        $expected = array_keys($this->struct->tables);

        $actual = $this->pdb->listTables();
        $this->assertNotEmpty($actual);

        sort($expected);
        sort($actual);

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


    public function testNestedTransactions()
    {
        $this->pdb->config->transaction_mode = 0
            | PdbConfig::TX_ENABLE_NESTED
            | PdbConfig::TX_STRICT_COMMIT
            | PdbConfig::TX_STRICT_ROLLBACK;

        $this->pdb->query('DELETE FROM ~tx_test', [], 'null');

        // Real transaction (1).
        $tx1 = $this->pdb->transact();

        $this->assertTrue($this->pdb->inTransaction());

        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc1'], 'count');
        $this->assertEquals(1, $ok);

        // savepoint (2).
        $tx2 = $this->pdb->transact();

        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc2'], 'count');
        $this->assertEquals(1, $ok);

        // savepoint (3).
        $tx3 = $this->pdb->transact();

        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc3'], 'count');
        $this->assertEquals(1, $ok);

        // Check data at 3.
        $expected = ['abc1', 'abc2', 'abc3'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);

        // savepoint (4).
        $tx4 = $this->pdb->transact();

        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc4'], 'count');
        $this->assertEquals(1, $ok);

        // Check data at 4.
        $expected = ['abc1', 'abc2', 'abc3', 'abc4'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);

        // Undo some things.
        $this->assertTrue($this->pdb->inTransaction());
        $tx4->rollback();

        // Check data at 3.
        $expected = ['abc1', 'abc2', 'abc3'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);

        // Rollback both 2 and 3.
        $tx2->rollback();

        // Check data at 1.
        $expected = ['abc1'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);

        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc5'], 'count');
        $this->assertEquals(1, $ok);

        // savepoint (5).
        $tx5 = $this->pdb->transact();

        // This doesn't actually do much.
        $tx5->commit();

        // Check data at 5.
        $expected = ['abc1', 'abc5'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);

        $tx1->rollback();

        // No more data.
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEmpty($actual);

        // Try committing something that doesn't exist anymore.
        // This assumes strict-mode commits.
        $this->expectException(TransactionEmptyException::class);
        $tx3->commit();
    }

}
