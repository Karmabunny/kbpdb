<?php

namespace karmabunny\pdb\Modifiers;

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbDataModifierInterface;

/**
 * Wrap an item with a 'concat' modifier.
 *
 * Such as:
 *
 * ```
 * $data = [ 'item' => new ConcatModifier($item) ];
 * $pdb->update('table', $data, ['id' => $id]);
 * ```
 *
 * Produces a query:
 *
 * `UPDATE ~table SET item = CONCAT(item, ?) WHERE id = ?`
 *
 * @package karmabunny\pdb
 */
class ConcatModifier implements PdbDataModifierInterface
{

    /** @var mixed */
    public $value;


    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }


    /** @inheritdoc */
    public function format()
    {
        return $this->value;
    }


    /** @inheritdoc */
    public function getBinding(Pdb $pdb, string $column): string
    {
        $column = $pdb->quoteField($column);
        return "{$column} = CONCAT({$column}, ?)";
    }
}
