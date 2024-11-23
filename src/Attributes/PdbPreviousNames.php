<?php

namespace karmabunny\pdb\Attributes;

use Attribute;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class PdbPreviousNames
{

    /** @var string[] */
    public $names = [];

    /**
     *
     * @param array $names
     */
    public function __construct(array $names)
    {
        $this->names = $names;
    }
}
