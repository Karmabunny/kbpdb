<?php

namespace kbtests\Models;

use karmabunny\kb\Collection;
use kbtests\Database;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModelInterface;
use karmabunny\pdb\PdbModelTrait;

/**
 * A base model.
 *
 * Using this library will typically require the user to create something
 * like this.
 *
 * This is a good chance to combine in Collections and other helpers.
 */
abstract class Record extends Collection implements PdbModelInterface
{
    use PdbModelTrait;

    /** @inheritdoc */
    public static function getConnection(): Pdb
    {
        return Database::getConnection();
    }
}
