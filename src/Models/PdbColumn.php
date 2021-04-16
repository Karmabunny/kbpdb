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
    public $name;

    /** @var string */
    public $type;

    /** @var bool */
    public $is_nullable = true;

    /** @var bool */
    public $is_primary;

    /**
     * This controls whether the field is auto incrementing.
     *
     * Given null, the field will not auto increment.
     * Otherwise, the increments will begin at the given number.
     *
     * @var int|null
     */
    public $auto_increment = null;

    /** @var string|null */
    public $default = null;

    /** @var string[] */
    public $previous_names = [];

    /** @var string */
    public $extra = '';

}
