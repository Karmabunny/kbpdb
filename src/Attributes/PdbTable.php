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

        // Previous names.
        $attributes = $this->model->getAttributes(PdbPreviousNames::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attribute = reset($attributes)) {
            $instance = $attribute->newInstance();
            $table->previous_names = $instance->names;
        }

        // Indexes.
        $attributes = $this->model->getAttributes(PdbIndex::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $index = $attribute->newInstance();
            $table->indexes[] = $index;
        }

        // Attributes.
        $attributes = $this->model->getAttributes(PdbAttributes::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attribute = reset($attributes)) {
            $instance = $attribute->newInstance();
            $table->attributes = $instance->attributes;
        }

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
            else {
                $column = null;
            }

            // Previous names.
            $attributes = $property->getAttributes(PdbPreviousNames::class, ReflectionAttribute::IS_INSTANCEOF);

            if ($attribute = reset($attributes)) {
                if (!$column) {
                    $this->errors[] = 'PdbPreviousNames declared without PdbColumn';
                }
                else {
                    $instance = $attribute->newInstance();
                    $table->previous_names = $instance->names;
                }
            }

            // Primary keys.
            if ($column and !$column->is_primary) {
                $attribute = $property->getAttributes(PdbPrimaryKey::class, ReflectionAttribute::IS_INSTANCEOF);

                if ($attribute = reset($attribute)) {
                    if (!$column) {
                        $this->errors[] = 'PdbPrimaryKey declared without PdbColumn';
                    }
                    else {
                        $column->is_primary = true;
                        $table->primary_key[] = $column->name;
                    }
                }
            }

            // Foreign keys + implicit index.
            $attribute = $property->getAttributes(PdbForeignKey::class, ReflectionAttribute::IS_INSTANCEOF);

            if ($attribute = reset($attribute)) {
                if (!$column) {
                    $this->errors[] = 'PdbForeignKey declared without PdbColumn';
                }
                else {
                    $fk = $attribute->newInstance();
                    $fk->from_table = $table->name;
                    $fk->from_column = $column->name;

                    $index = new PdbIndex([$column->name], $fk->is_unique);

                    $table->foreign_keys[] = $fk;
                    $table->indexes[] = $index;
                }
            }

            // Attributes.
            $attributes = $property->getAttributes(PdbAttributes::class, ReflectionAttribute::IS_INSTANCEOF);

            if ($attribute = reset($attributes)) {
                if (!$column) {
                    $this->errors[] = 'PdbAttributes declared without PdbColumn';
                }
                else {
                    $instance = $attribute->newInstance();
                    $column->attributes = $instance->attributes;
                }
            }
        }

        return [ $table ];
    }
}
