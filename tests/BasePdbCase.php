<?php

namespace kbtests;

use karmabunny\kb\Uuid;
use karmabunny\pdb\Drivers\PdbMysql;
use karmabunny\pdb\Drivers\PdbPgsql;
use karmabunny\pdb\Drivers\PdbSqlite;
use karmabunny\pdb\Exceptions\TransactionEmptyException;
use karmabunny\pdb\Exceptions\TransactionNameException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
use karmabunny\pdb\PdbLog;
use karmabunny\pdb\PdbMutex;
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


    public function testQueryBuilder(): void
    {
        $id1 = $this->pdb->insert('clubs', [
            'uid' => Uuid::uuid4(),
            'date_added' => $this->pdb->now(),
            'date_modified' => $this->pdb->now(),
            'active' => 1,
            'name' => 'Club 1',
            'status' => 'active',
            'founded' => $this->pdb->now(),
            'type' => 'one',
        ]);

        $id2 = $this->pdb->insert('clubs', [
            'uid' => Uuid::uuid4(),
            'date_added' => $this->pdb->now(),
            'date_modified' => $this->pdb->now(),
            'active' => 1,
            'name' => 'Club 2',
            'status' => 'active',
            'founded' => $this->pdb->now(),
            'type' => 'two',
        ]);

        $expected1 = $this->pdb->get('clubs', $id1, false);
        $this->assertNotNull($expected1);
        $this->assertEquals('Club 1', $expected1['name']);

        $expected2 = $this->pdb->get('clubs', $id2, false);
        $this->assertNotNull($expected2);
        $this->assertEquals('Club 2', $expected2['name']);

        $subQuery = $this->pdb->find('clubs')
            ->where(['type' => 'one']);

        $query = $this->pdb->find('clubs as club')
            ->with($subQuery->select('id as _id'), 'subQuery')
            ->join('subQuery', 'subQuery._id = club.id');

        $actual1 = $query->all();
        $this->assertEquals([$expected1], $actual1);

        $subQuery->where(['type' => 'two']);
        $actual2 = $query->all();
        $this->assertEquals([$expected2], $actual2);
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

    /**
     * Test that withTransaction() properly handles transactions.
     *
     * @dataProvider dataActive
     */
    public function testWithTransaction($active): void
    {
        $this->pdb->config->transaction_mode = PdbConfig::TX_STRICT_COMMIT | PdbConfig::TX_STRICT_ROLLBACK;

        // Ensure that withTransaction() within a transaction uses savepoints.
        if ($active) {
            $this->pdb->config->transaction_mode |= PdbConfig::TX_ENABLE_NESTED;
        }

        $transaction = $this->pdb->transact();

        // Create initial data
        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc1'], 'count');
        $this->assertEquals(1, $ok);

        // Run transaction block
        $result = $this->pdb->withTransaction(function($pdb, $transaction) use ($active) {
            $this->assertEquals($active, $transaction->isSavepoint());

            $ok = $pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc2'], 'count');
            $this->assertEquals(1, $ok);
            return 'success';
        });

        $this->assertEquals('success', $result);

        $transaction->commit();

        // Verify both inserts committed
        $expected = ['abc1', 'abc2'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);
    }


    /**
     * Test that withTransaction() rolls back on exceptions.
     *
     * Without a wrapped nested transaction it should behave the same with both
     * nested queries enabled and without.
     *
     * @dataProvider dataActive
     */
    public function testWithTransactionRollback($active): void
    {
        $this->pdb->config->transaction_mode = PdbConfig::TX_STRICT_COMMIT | PdbConfig::TX_STRICT_ROLLBACK;

        if ($active) {
            $this->pdb->config->transaction_mode |= PdbConfig::TX_ENABLE_NESTED;
        }

        // Create initial data
        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc1'], 'count');
        $this->assertEquals(1, $ok);

        // Run transaction block that throws
        try {
            $this->pdb->withTransaction(function($pdb, $transaction) {
                $this->assertFalse($transaction->isSavepoint());

                $ok = $pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc2'], 'count');
                $this->assertEquals(1, $ok);
                throw new \Exception('Test exception');
            });
        }
        catch (\Exception $error) {
            $this->assertEquals('Test exception', $error->getMessage());
        }

        // Verify only initial insert remains
        $expected = ['abc1'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test behaviour of withTransaction() when nested queries are enabled.
     *
     * @dataProvider dataActive
     */
    public function testWithTransactionRollbackNested($active): void
    {
        $this->pdb->config->transaction_mode = PdbConfig::TX_STRICT_COMMIT | PdbConfig::TX_STRICT_ROLLBACK;

        // Without nested queries withTransaction() is severely nerfed.
        if ($active) {
            $this->pdb->config->transaction_mode |= PdbConfig::TX_ENABLE_NESTED;
        }

        $transaction = $this->pdb->transact();

        // Create initial data
        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc1'], 'count');
        $this->assertEquals(1, $ok);

        // Run transaction block that throws
        try {
            $this->pdb->withTransaction(function($pdb, $transaction) use ($active) {
                $this->assertEquals($active, $transaction->isSavepoint());

                $ok = $pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc2'], 'count');
                $this->assertEquals(1, $ok);
                throw new \Exception('Test exception');
            });
        }
        catch (\Exception $error) {
            $this->assertEquals('Test exception', $error->getMessage());
        }

        $transaction->commit();

        $actual = $this->pdb->find('tx_test')->column('name');

        if ($active) {
            // Verify only initial insert remains
            $expected = ['abc1'];
            $this->assertEquals($expected, $actual);
        }
        else {
            // Without nested queries, the nested block is still committed.
            $expected = ['abc1', 'abc2'];
            $this->assertEquals($expected, $actual);
        }
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


    /**
     * @dataProvider dataActive
     */
    public function testTransactionStrictCommit($active): void
    {
        $this->pdb->config->transaction_mode = $active ? PdbConfig::TX_STRICT_COMMIT : 0;
        $this->assertEquals($active, (bool) ($this->pdb->config->transaction_mode & PdbConfig::TX_STRICT_COMMIT));

        if ($active) {
            $this->expectException(TransactionEmptyException::class);
        }

        $this->pdb->commit();
    }


    /**
     * @dataProvider dataActive
     */
    public function testTransactionStrictRollback($active): void
    {
        $this->pdb->config->transaction_mode = $active ? PdbConfig::TX_STRICT_ROLLBACK : 0;
        $this->assertEquals($active, (bool) ($this->pdb->config->transaction_mode & PdbConfig::TX_STRICT_ROLLBACK));

        if ($active) {
            $this->expectException(TransactionEmptyException::class);
        }

        $this->pdb->rollback();
    }


    /**
     * @dataProvider dataActive
     */
    public function testTransactionCommitKeys($active): void
    {
        $this->pdb->config->transaction_mode = $active ? PdbConfig::TX_FORCE_COMMIT_KEYS : 0;

        $this->assertEquals($active, (bool) ($this->pdb->config->transaction_mode & PdbConfig::TX_FORCE_COMMIT_KEYS));

        // Create initial data.
        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc1'], 'count');
        $this->assertEquals(1, $ok);

        // Start transaction.
        $tx1 = $this->pdb->transact();

        // Insert more data.
        $ok = $this->pdb->query('INSERT INTO ~tx_test (name) VALUES (?)', ['abc2'], 'count');
        $this->assertEquals(1, $ok);

        // Verify data.
        $expected = ['abc1', 'abc2'];
        $actual = $this->pdb->find('tx_test')->column('name');
        $this->assertEquals($expected, $actual);

        if ($active) {
            $this->expectException(TransactionNameException::class);
            $this->pdb->commit();
        }
        else {
            $this->pdb->commit($tx1);
        }
    }


    public function dataActive(): array
    {
        return [
            'active' => [true],
            'NOT active' => [false],
        ];
    }


    protected function createMutex(string $name)
    {
        return new PdbMutex($this->pdb, $name);
    }


    public function testMutexLock()
    {
        if (
            !$this->pdb instanceof PdbPgsql
            and !$this->pdb instanceof PdbMysql
        ) {
            $this->markTestSkipped('Skipping mutex tests, not supported.');
        }

        $time = microtime(true);
        $lock1 = $this->createMutex('test:1');
        $this->assertTrue($lock1->acquire(0));

        // No existing lock - no waiting, got a lock.
        $this->assertLessThan(0.01, microtime(true) - $time);

        // New lock, no collision, no wait.
        $lock2 = $this->createMutex('test:2');
        $this->assertTrue($lock2->acquire(0));

        // Existing lock, collision, immediate failure.
        $time = microtime(true);
        $lock3 = $this->createMutex('test:1');
        $this->assertFalse($lock3->acquire(0));
        $this->assertLessThan(0.01, microtime(true) - $time);

        // Existing lock, collision, failure after timeout.
        $time = microtime(true);
        $lock3 = $this->createMutex('test:1');
        $this->assertFalse($lock3->acquire(0.5));
        $this->assertGreaterThan(0.5, microtime(true) - $time);

        $this->assertTrue($lock1->release());

        // Try again - with success.
        $lock3 = $this->createMutex('test:1');
        $this->assertTrue($lock3->acquire(0));
        $this->assertEquals($lock1->name, $lock3->name);
    }

}
