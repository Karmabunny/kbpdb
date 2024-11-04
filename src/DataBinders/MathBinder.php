<?php

namespace karmabunny\pdb\DataBinders;

use InvalidArgumentException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbDataBinderInterface;

/**
 * A data binder for performing math operations on update.
 *
 * Note, if used for an insert the whole 'value' is inserted without any operation.
 *
 * E.g.
 *
 * ```
 * $update = [
 *     'date_modified' => Pdb::now(),
 *     'counter' => MathBinder::add(10),
 * ];
 * Pdb::update('my_table', $update, ['id' => $id]);
 * ```
 *
 * @package karmabunny\pdb
 */
class MathBinder implements PdbDataBinderInterface
{

    const OPERATOR_ADD = '+';

    const OPERATOR_SUBTRACT = '-';

    const OPERATOR_MULTIPLY = '*';

    const OPERATOR_DIVIDE = '/';

    const OPERATOR_MODULO = '%';

    const OPERATORS = [
        self::OPERATOR_ADD,
        self::OPERATOR_SUBTRACT,
        self::OPERATOR_MULTIPLY,
        self::OPERATOR_DIVIDE,
        self::OPERATOR_MODULO,
    ];


    /** @var int */
    public $value;

    /** @var string */
    public $operator;

    /** @var bool */
    public $inverse;


    /**
     * Use the the shorthand add/subtract/etc helpers.
     *
     * @param int|float $value
     * @param string $operator one of `self::OPERATORS`
     * @param bool $invoice swap the operation: `{value} X {column}`
     */
    public function __construct($value, string $operator, bool $inverse)
    {
        if (!in_array($operator, self::OPERATORS)) {
            throw new InvalidArgumentException("Invalid operator: {$operator}");
        }

        $this->value = $value;
        $this->operator = $operator;
        $this->inverse = $inverse;
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

        if (!$this->inverse) {
            return "{$column} = {$column} {$this->operator} ?";
        }
        else {
            return "{$column} = ? {$this->operator} {$column}";
        }
    }


    /**
     * Add a value to the column.
     *
     * Note, inverse here is pointless.
     *
     * @param int|float $value
     * @param bool $inverse
     * @return MathBinder
     */
    public static function add($value, $inverse = false)
    {
        return new MathBinder($value, self::OPERATOR_ADD, $inverse);
    }


    /**
     * Subtract a value from the column.
     *
     * @param int|float $value
     * @param bool $inverse
     * @return MathBinder
     */
    public static function subtract($value, $inverse = false)
    {
        return new MathBinder($value, self::OPERATOR_SUBTRACT, $inverse);
    }


    /**
     * Multiply the column by this value.
     *
     * @param int|float $value
     * @param bool $inverse
     * @return MathBinder
     */
    public static function multiply($value, $inverse = false)
    {
        return new MathBinder($value, self::OPERATOR_MULTIPLY, $inverse);
    }


    /**
     * Divide the column by this value.
     *
     * @param int|float $value
     * @param bool $inverse
     * @return MathBinder
     */
    public static function divide($value, $inverse = false)
    {
        return new MathBinder($value, self::OPERATOR_DIVIDE, $inverse);
    }


    /**
     * Perform a remainder division on the column.
     *
     * @param int|float $value
     * @param bool $inverse
     * @return MathBinder
     */
    public static function modulo($value, $inverse = false)
    {
        return new MathBinder($value, self::OPERATOR_MODULO, $inverse);
    }
}

