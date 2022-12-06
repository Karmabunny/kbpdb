<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;

final class Database
{
    public static function getConnection($type = 'mysql')
    {
        static $pdb;

        if (!isset($pdb)) {
            $config = require __DIR__ . '/config.php';
            $pdb = Pdb::create($config);
        }
        return $pdb;
    }


    public static function sync(string $type)
    {
        $pdb = self::getConnection($type);

        $struct = new PdbParser();
        $struct->loadXml(__DIR__ . '/db_struct.xml');
        $struct->sanityCheck();

        $sync = new PdbSync($pdb);
        $sync->migrate($struct);

        return $sync->execute();
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
