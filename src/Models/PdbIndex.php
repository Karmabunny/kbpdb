<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbIndex extends Collection
{
    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var string[] */
    public $columns = [];

}
