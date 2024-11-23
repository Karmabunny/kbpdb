<?php

namespace kbtests\Attributes;

use Attribute;
use karmabunny\pdb\Attributes\PdbBaseTable;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Models\PdbTable;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_CLASS)]
class CategoriesTable extends PdbBaseTable
{

    /**
     *
     * @return PdbTable[]
     */
    public function getTables(): array
    {
        $table = $this->getTableName();

        $category = new PdbTable([
            'name' => $table . '_categories',
            'columns' => [
                new PdbColumn(['name' => 'id', 'type' => 'INT UNSIGNED']),
                new PdbColumn(['name' => 'name', 'type' => 'VARCHAR(200)']),
            ],
            'primary_key' => ['id'],
        ]);

        $joiner = new PdbTable([
            'name' => $table . '_cat_join',
            'columns' => [
                new PdbColumn(['name' => 'category_id', 'type' => 'INT UNSIGNED']),
                new PdbColumn(['name' => 'record_id', 'type' => 'INT UNSIGNED']),
            ],
            'indexes' => [
                new PdbIndex(['columns' => ['category_id']]),
                new PdbIndex(['columns' => ['record_id']]),
            ],
            'primary_key' => [
                'category_id',
                'record_id',
            ],
            'foreign_keys' => [
                new PdbForeignKey([
                    'from_table' => $table . '_cat_join',
                    'from_column' => 'category_id',
                    'to_table' => $table . '_categories',
                    'to_column' => 'id',
                    'update_rule' => 'restrict',
                    'delete_rule' => 'cascade',
                ]),
                new PdbForeignKey([
                    'from_table' => $table . '_cat_join',
                    'from_column' => 'record_id',
                    'to_table' => $table,
                    'to_column' => 'id',
                    'update_rule' => 'restrict',
                    'delete_rule' => 'cascade',
                ]),
            ],
        ]);

        return [
            $category,
            $joiner,
         ];
    }
}
