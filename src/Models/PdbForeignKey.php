<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMNode;
use karmabunny\kb\Collection;
use karmabunny\pdb\PdbStructWriterInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbForeignKey extends Collection implements PdbStructWriterInterface
{
    /** @var string|null */
    public $constraint_name;

    /** @var string non-prefixed */
    public $from_table;

    /** @var string */
    public $from_column;

    /** @var string non-prefixed */
    public $to_table;

    /** @var string */
    public $to_column;

    /** @var string */
    public $update_rule;

    /** @var string */
    public $delete_rule;



    /**
     *
     * @param PdbTable[] $tables
     * @param string $table_name
     * @param string $column_name
     * @return PdbColumn|null
     */
    private static function getColumn(array $tables, string $table_name, string $column_name) {
        /** @var PdbTable|null $table */
        $table = $tables[$table_name] ?? null;
        if (!$table) return null;
        return $table->columns[$column_name] ?? null;
    }


    /**
     *
     * @param PdbTable[] $tables
     * @param PdbTable $table
     * @return string[]
     */
    public function check(array $tables, PdbTable $table): array
    {
        $errors = [];

        // Check types match on both sites of the reference
        // Check column is nullable if SET NULL is used
        // Check the TO side of the reference is indexed

        $from_column = self::getColumn($tables, $this->from_table, $this->from_column);
        $to_column = self::getColumn($tables, $this->to_table, $this->to_column);

        if (!$from_column) {
            $errors[] = "Foreign key \"{$this->from_column}\" on unknown column \"{$this->from_table}.{$this->from_column}\"";
            return $errors;
        }

        if (!$to_column) {
            $errors[] = "Foreign key \"{$this->from_column}\" points to unknown column \"{$this->to_table}.{$this->to_column}\"";
            return $errors;
        }

        if ($to_column->type != $from_column->type) {
            $errors[] = "Foreign key \"{$this->from_column}\" column type mismatch ({$to_column->type} vs {$from_column->type}))";
        }

        if ($this->update_rule === 'set-null' and !$from_column->is_nullable) {
            $errors[] = "Foreign key \"{$this->from_column}\" update action to SET NULL but column doesn't allow nulls";
        }

        if ($this->delete_rule == 'set-null' and !$from_column->is_nullable) {
            $errors[] = "Foreign key \"{$this->from_column}\" delete action to SET NULL but column doesn't allow nulls";
        }

        $to_table = $tables[$this->to_table] ?? null;
        $index_found = false;

        if ($to_table) {
            // Lucky us, it's a classic FK => PK relation.
            if ($to_table->primary_key[0] == $this->to_column) {
                $index_found = true;
            }
            // Or it points to an index somewhere.
            else {
                foreach ($to_table->indexes as $index) {
                    // Apparently only the first column on index.
                    $column = reset($index->columns);
                    if (!$column) continue;

                    // Found it!
                    if ($column == $this->to_column) {
                        $index_found = true;
                        break;
                    }
                }
            }
        }

        if (!$index_found) {
            $errors[] = "Foreign key \"{$this->from_column} referenced column ({$this->to_table}.{$this->to_column})) is not first column in an index";
        }

        return $errors;
    }


    /** @inheritdoc */
    public function toXml(DOMDocument $doc, bool $wrap_index = false): DOMNode
    {
        $fk = $doc->createElement('foreign-key');

        $fk->setAttribute('table', $this->to_table);
        $fk->setAttribute('column', $this->to_column);
        $fk->setAttribute('update', $this->update_rule);
        $fk->setAttribute('delete', $this->delete_rule);

        if ($wrap_index) {
            $column = $doc->createElement('col');
            $column->setAttribute('name', $this->from_column);

            $index = $doc->createElement('index');
            $index->appendChild($column);
            $index->appendChild($fk);
            return $index;
        }
        else {
            return $fk;
        }
    }

}
