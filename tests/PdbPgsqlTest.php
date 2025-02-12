<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;

/**
 *
 */
class PdbPgsqlTest extends BasePdbCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->pdb = Pdb::create([
            'type' => PdbConfig::TYPE_PGSQL,
            'host' => 'postgres',
            'user' => 'postgres',
            'pass' => 'password',
            'database' => 'kbpdb',
        ]);

        try {
            $this->pdb->getConnection();
        }
        catch (ConnectionException $error) {
            $this->markTestSkipped('No DB connection');
        }
    }
}
