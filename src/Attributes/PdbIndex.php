<?php

namespace karmabunny\pdb\Attributes;

use Attribute;
use karmabunny\pdb\Models\PdbIndex as PdbIndexModel;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_CLASS)]
class PdbIndex extends PdbIndexModel
{
    /**
     *
     * @param array $columns
     * @param bool $unique
     */
    public function __construct(array $columns, bool $unique = false)
    {
        parent::__construct([
            'columns' => $columns,
            'type' => $unique ? self::TYPE_UNIQUE : self::TYPE_INDEX,
        ]);
    }
}
