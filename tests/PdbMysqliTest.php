<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;

/**
 *
 */
class PdbMysqliTest extends BasePdbCase
{
    public function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__ . '/config.php';
        $config['type'] = 'mysqli';

        $this->pdb = Pdb::create($config);

        try {
            $this->pdb->getConnection();
        }
        catch (ConnectionException $error) {
            $this->markTestSkipped('No DB connection');
        }
    }
}
