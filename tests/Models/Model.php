<?php

namespace kbtests\Models;

use ArrayIterator;
use IteratorAggregate;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModel;
use karmabunny\pdb\PdbAuditTrait;
use karmabunny\pdb\PdbModelTrait;
use Traversable;

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

    protected static $pdb;


    protected static function getConnection(): Pdb
    {
        if (!isset(self::$pdb)) {
            $config = require __DIR__ . '/../config.php';
            self::$pdb = new Pdb($config);
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
