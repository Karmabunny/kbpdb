<?php

namespace karmabunny\pdb\Attributes;

use Attribute;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PdbPrimaryKey extends PdbColumn
{

    public $auto_id = false;

    public function __construct($auto_id = false, $config = [])
    {
        $config['type'] = $config['type'] ?? 'INT UNSIGNED';
        $config['auto_increment'] = $config['auto_increment'] ?? true;
        $config['is_nullable'] = $config['is_nullable'] ?? false;
        $config['is_primary'] = true;

        $this->auto_id = $auto_id;
        parent::__construct($config['type'], $config);
    }
}
