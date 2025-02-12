<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbLog;
use karmabunny\pdb\PdbParser;
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


    public function testSchemaExtraction()
    {
        $struct = new PdbParser();
        $struct->loadXml(__DIR__ . '/db_struct.xml');
        $struct->sanityCheck();

        $sync = new PdbSync($this->pdb);
        $sync->migrate($struct);
        $log = $sync->execute();
        // PdbLog::print($log);

        // Get the schema.
        $schema = $this->pdb->getSchema();
        $tables = $schema->getTables();

        // Basic assertions.
        $this->assertNotEmpty($tables, 'No tables found.');
        $this->assertEquals($struct->getTableNames(), $schema->getTableNames());

        // Detailed assertions.
        foreach ($tables as $name => $table) {
            $original = $struct->getTable($name);

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


    public function testSchemaWrite()
    {
        // TODO I did write an XML writer. But I've lost it.
    }
}
