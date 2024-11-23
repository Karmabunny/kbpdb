<?php

namespace karmabunny\pdb\Attributes;

use Attribute;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PdbAttributes
{

    /** @var array */
    public $attributes = [];


    /**
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->attributes = $config;
    }
}
