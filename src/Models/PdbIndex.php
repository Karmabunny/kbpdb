<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbIndex extends Collection
{

    const TYPE_INDEX = 'index';
    const TYPE_UNIQUE = 'unique';

    /** @var string */
    public $type = 'index';

    /** @var string[] */
    public $columns = [];

}
