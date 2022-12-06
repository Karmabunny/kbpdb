<?php

namespace karmabunny\pdb\DataBinders;

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbDataBinderInterface;

/**
 * Wrap an item with a 'concat' modifier.
 *
 * Such as:
 *
 * ```
 * $data = [ 'item' => new ConcatDataBinder($item) ];
 * $pdb->update('table', $data, ['id' => $id]);
 * ```
 *
 * Produces a query:
 *
 * `UPDATE ~table SET item = CONCAT(item, ?) WHERE id = ?`
 *
 * @package karmabunny\pdb
 */
class ConcatDataBinder implements PdbDataBinderInterface
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
    public function getBindingValue()
    {
        return $this->value;
    }


    /** @inheritdoc */
    public function getBindingQuery(Pdb $pdb, string $column): string
    {
        $column = $pdb->quoteField($column);
        return "{$column} = CONCAT({$column}, ?)";
    }
}
