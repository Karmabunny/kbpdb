<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;

/**
 *
 */
class PdbMysqlTest extends BasePdbCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->pdb = Pdb::create(require __DIR__ . '/config.php');

        try {
            $this->pdb->getConnection();
        }
        catch (ConnectionException $error) {
            $this->markTestSkipped('No DB connection');
        }
    }
}
