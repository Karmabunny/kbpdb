<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
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


    public static function isConnected(): bool
    {
        $pdb = self::getConnection();
        try {
            $pdb->getConnection();
            return true;
        }
        catch (ConnectionException $error) {
            return false;
        }
    }
}
