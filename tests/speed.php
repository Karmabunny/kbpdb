<?php
use karmabunny\pdb\Pdb;

require __DIR__ . '/../vendor/autoload.php';

class Timing {
    private static $sections;
    private static $section;
    private static $start;

    public static function start($section)
    {
        self::$section = $section;
        self::$start = microtime(true);
    }

    public static function stop()
    {
        $end = microtime(true);
        self::$sections[self::$section] = $end - self::$start;
    }

    public static function report()
    {
        foreach (self::$sections as $name => $time_us) {
            echo str_pad($name, 60);
            echo str_pad(number_format($time_us * 1000, 2), 10, ' ', STR_PAD_LEFT), 'ms';
            echo PHP_EOL;
        }
    }

    public static function clear()
    {
        self::$sections = [];
    }
}

speedTestPdb();
Timing::report();


function speedTestPdb()
{
    $config = require __DIR__ . '/config.php';
    $pdb = Pdb::create($config);

    $q = "CREATE TEMPORARY TABLE ~speedtest (
        id INT NOT NULL PRIMARY KEY
    )";
    $pdb->query($q, [], 'null');

    $pdb->transact();
    Timing::start('Inserts via Pdb::query');
    for ($i = 1; $i <= 5000; $i += 1) {
        $q = "INSERT INTO ~speedtest SET id = ?";
        $pdb->query($q, [$i], 'null');
    }
    Timing::stop();
    $pdb->commit();

    $pdb->transact();
    Timing::start('Inserts via Pdb::insert');
    for ($i = 5001; $i <= 10000; $i += 1) {
        $pdb->insert('speedtest', ['id' => $i]);
    }
    Timing::stop();
    $pdb->commit();

    $pdb->transact();
    Timing::start('Inserts via Pdb::prepare + Pdb::execute');
    $q = "INSERT INTO ~speedtest SET id = ?";
    $stmt = $pdb->prepare($q);
    for ($i = 10001; $i <= 15000; $i += 1) {
        $pdb->execute($stmt, [$i], 'null');
    }
    Timing::stop();
    $pdb->commit();

    $q = "DROP TABLE ~speedtest";
    $pdb->query($q, [], 'null');
}
