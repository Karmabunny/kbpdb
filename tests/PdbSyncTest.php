<?php

use karmabunny\pdb\PdbLog;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;
use kbtests\Database;
use PHPUnit\Framework\TestCase;


class PdbSyncTest extends TestCase
{

    public function testMigrate(): void
    {
        $pdb = Database::getConnection();
        $pdb->query('DROP TABLE IF EXISTS ~clubs', [], 'null');

        $sync = new PdbSync($pdb);

        $struct = new PdbParser();
        $struct->loadXml(__DIR__ . '/db_struct.xml');
        $struct->sanityCheck();

        $sync->migrate($struct);
        $log = $sync->getMigration();

        $this->assertNotEmpty($log);

        // TODO some fine-grain assertions here.

        // foreach ($log as $item) {
        //     echo $item, PHP_EOL;
        // }
    }
}
