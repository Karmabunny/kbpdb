<?php

// namespace Models;

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModel;
use karmabunny\pdb\PdbAuditTrait;
use karmabunny\pdb\PdbModelTrait;

require_once __DIR__ . '/../config.php';

/**
 * A base model.
 *
 * Using this library will typically the user to create something
 * like this.
 *
 * The Pdb object needs to be cached _somewhere_.
 *
 * Also good because the PdbModel is pretty lightweight.
 * This is a good chance to combine in Collections and other helpers.
 */
abstract class Model implements PdbModel, IteratorAggregate
{
    use PdbModelTrait;
    use PdbAuditTrait;

    protected static $pdb;


    protected static function getConnection(): Pdb
    {
        if (!isset(self::$pdb)) {
            self::$pdb = new Pdb(CONFIG);
        }
        return self::$pdb;
    }


    public function __construct($config = [])
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }


    public function getIterator(): Traversable
    {
        return new ArrayIterator($this);
    }
}
