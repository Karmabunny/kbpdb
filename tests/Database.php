<?php

namespace kbtests;

use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;

final class Database
{
    public static function getConnection($type = 'mysql', $refresh = false): Pdb
    {
        static $pdb = [];

        if ($refresh) {
            unset($pdb[$type]);
        }

        if (!isset($pdb[$type])) {
            if ($type === 'mysql') {
                $config = require __DIR__ . '/config.php';
                $pdb[$type] = Pdb::create($config);
            }
            else if ($type === 'sqlite') {
                $pdb[$type] = Pdb::create([
                    'type' => PdbConfig::TYPE_SQLITE,
                    'dsn' => __DIR__ . '/db.sqlite',
                ]);
            }
            else {
                throw new \InvalidArgumentException("Invalid database type: {$type}");
            }
        }

        return $pdb[$type];
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


    public static function drop(string $type)
    {
        $pdb = self::getConnection($type);
        $tables = $pdb->getTableNames('', false);

        foreach ($tables as $table) {
            $pdb->query("DROP TABLE IF EXISTS {$table}", [], 'null');
        }
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
