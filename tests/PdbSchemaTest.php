<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbLog;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSchemaInterface;
use karmabunny\pdb\PdbSync;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema extraction functionality.
 */
class PdbSchemaTest extends TestCase
{

    /** @var Pdb */
    public $pdb;


    public function setUp(): void
    {
        parent::setUp();

        try {
            $this->pdb ??= Database::getConnection();
            $this->pdb->getConnection();

            Database::drop('mysql');
        }
        catch (ConnectionException $error) {
            $this->markTestSkipped('No DB connection.');
        }
    }


    public function sync()
    {
        $struct = new PdbParser();
        $struct->loadXml(__DIR__ . '/db_struct.xml');
        $struct->sanityCheck();

        $sync = new PdbSync($this->pdb);
        $sync->migrate($struct);
        $log = $sync->execute();
        // PdbLog::print($log);

        return $struct;
    }


    public function assertSchema(PdbSchemaInterface $expected, PdbSchemaInterface $actual)
    {
        $actualTables = $actual->getTables();
        $expectedTables = $expected->getTables();

        if (!empty($expectedTables)) {
            $this->assertNotEmpty($actualTables);
        }

        $this->assertEquals($expected->getTableNames(), $actual->getTableNames());

        foreach ($actualTables as $name => $table) {
            $original = $expectedTables[$name] ?? null;
            $this->assertNotNull($original);

            // Compare columns.
            $this->assertEquals(
                array_keys($original->columns),
                array_keys($table->columns),
                "Column mismatch for table: {$name}.",
            );

            // Compare indexes.
            $this->assertEquals(
                array_keys($original->indexes),
                array_keys($table->indexes),
                "Index mismatch for table: {$name}.",
            );

            // Compare foreign keys.
            $this->assertEquals(
                array_keys($original->foreign_keys),
                array_keys($table->foreign_keys),
                "Foreign key mismatch for table: {$name}.",
            );
        }
    }


    public function testSchemaExtraction()
    {
        $struct = $this->sync();
        $schema = $this->pdb->getSchema();
        $this->assertSchema($struct, $schema);
    }


    public function testSchemaReapply()
    {
        $struct = $this->sync();

        // Get the schema.
        $schema1 = $this->pdb->getSchema();
        $this->assertNotEmpty($schema1->getTableNames(), 'No tables found.');

        Database::drop('mysql');

        $schema2 = $this->pdb->getSchema();
        $this->assertEmpty($schema2->getTableNames());

        $sync = new PdbSync($this->pdb);
        $sync->migrate($struct);
        $log = $sync->execute();
        // PdbLog::print($log);

        $schema3 = $this->pdb->getSchema();
        $this->assertSchema($struct, $schema3);
    }


    public function testSchemaWrite()
    {
        // TODO I did write an XML writer. But I've lost it.
    }
}
