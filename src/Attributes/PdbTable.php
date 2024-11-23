<?php

namespace karmabunny\pdb\Attributes;

use Attribute;
use karmabunny\pdb\Models\PdbTable as PdbTableModel;
use ReflectionAttribute;

/**
 *
 * @package karmabunny\pdb
 */
#[Attribute(Attribute::TARGET_CLASS)]
class PdbTable extends PdbBaseTable
{

    /** @inheritdoc */
    public function getTables(): array
    {
        $table = new PdbTableModel();
        $table->name = $this->getTableName();

        // Read in columns.
        $properties = $this->model->getProperties();

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            // Column def.
            $attribute = $property->getAttributes(PdbColumn::class, ReflectionAttribute::IS_INSTANCEOF);

            if ($attribute = reset($attribute)) {
                $column = $attribute->newInstance();

                if (!$column instanceof PdbPrimaryKey) {
                    $column->name = $property->name;
                    $table->columns[$column->name] = $column;
                }
                else if ($column->auto_id) {
                    $column->name = $property->name;
                    $table->columns[$column->name] = $column;
                    $table->primary_key[] = $column->name;
                }
            }
        }

        return [ $table ];
    }
}
