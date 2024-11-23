<?php

namespace karmabunny\pdb\Attributes;

use Attribute;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PdbEnumColumn extends PdbColumn
{
    /**
     *
     * @param string[] $values
     * @param array $config
     */
    public function __construct(array $values, $config = [])
    {
        foreach ($values as &$value) {
            $value = addcslashes($value, '\'');
            $value = "'{$value}'";
        }
        unset($value);

        $values = implode(',', $values);
        parent::__construct("ENUM({$values})", $config);
    }
}
