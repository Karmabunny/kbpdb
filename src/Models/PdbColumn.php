<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbColumn extends Collection
{
    /** @var string */
    public $column_name;

    /** @var string */
    public $column_type;

    /** @var bool */
    public $is_nullable;

    /** @var bool */
    public $is_primary;

    /** @var string */
    public $column_default;

    /** @var string */
    public $extra;

}
