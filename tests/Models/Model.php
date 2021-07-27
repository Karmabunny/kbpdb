<?php

namespace kbtests\Models;

use ArrayIterator;
use kbtests\Database;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModel;
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
abstract class Model extends PdbModel
{

    protected static $pdb;


    public static function getConnection(): Pdb
    {
        return Database::getConnection();
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
