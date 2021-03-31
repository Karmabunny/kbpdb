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
    public $source_table;

    /** @var string */
    public $source_column;

    /** @var string */
    public $referenced_table;

    /** @var string */
    public $referenced_column;

    /** @var string */
    public $update_rule;

    /** @var string */
    public $delete_rule;
}
