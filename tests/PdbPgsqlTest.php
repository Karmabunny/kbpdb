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
        $this->pdb = Pdb::create([
            'type' => PdbConfig::TYPE_PGSQL,
            'host' => getenv('SITES_POSTGRES_HOSTNAME') ?: 'postgres',
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

        parent::setUp();
    }
}
