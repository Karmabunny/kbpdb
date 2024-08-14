<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbView extends Collection
{
    /** @var string */
    public $name;

    /** @var string */
    public $sql;

    /** @var string */
    public $checksum;
}
