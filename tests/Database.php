<?php

namespace kbtests;

use karmabunny\pdb\Pdb;


final class Database
{
    public static function getConnection()
    {
        static $pdb;

        if (!isset($pdb)) {
            $config = require __DIR__ . '/config.php';
            $pdb = Pdb::create($config);
        }
        return $pdb;
    }
}
