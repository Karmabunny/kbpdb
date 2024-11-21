<?php

namespace karmabunny\pdb\Attributes;

use Attribute;
use karmabunny\pdb\Models\PdbColumn as PdbColumnModel;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PdbColumn extends PdbColumnModel
{
    /**
     *
     * @param string $type
     * @param array $config
     */
    public function __construct(string $type, array $config = [])
    {
        $config['type'] = $type;
        parent::__construct($config);
    }
}
