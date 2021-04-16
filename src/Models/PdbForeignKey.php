<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbForeignKey extends Collection
{
    /** @var string */
    public $constraint_name;

    /** @var string */
    public $from_table;

    /** @var string */
    public $from_column;

    /** @var string */
    public $to_table;

    /** @var string */
    public $to_column;

    /** @var string */
    public $update_rule;

    /** @var string */
    public $delete_rule;
}
