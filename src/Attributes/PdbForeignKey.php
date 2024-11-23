<?php

namespace karmabunny\pdb\Attributes;

use Attribute;
use karmabunny\pdb\Models\PdbForeignKey as PdbForeignKeyModel;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PdbForeignKey extends PdbForeignKeyModel
{

    /** @var bool from the parent index */
    public $is_unique = false;


    /**
     *
     * @param string $table
     * @param string $column
     * @param string $update
     * @param string $delete
     */
    public function __construct(string $table, string $column, string $update, string $delete, bool $unique = false)
    {
        parent::__construct([
            'to_table' => $table,
            'to_column' => $column,
            'update_rule' => $update,
            'delete_rule' => $delete,
            'is_unique' => $unique,
        ]);
    }
}
