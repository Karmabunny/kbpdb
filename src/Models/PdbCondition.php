<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;
use karmabunny\kb\Arrays;

/**
 *
 * @package karmabunny\pdb
 */
class PdbCondition
{

    const EQUAL = '=';
    const LESS_THAN_EQUAL = '<=';
    const GREATER_THAN_EQUAL = '>=';
    const LESS_THAN = '<';
    const GREATER_THAN = '>';
    const NOT_EQUAL = '!=';
    const NOT_EQUAL_ALT = '<>';
    const IS = 'IS';
    const IS_NOT = 'IS NOT';
    const BETWEEN = 'BETWEEN';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';
    const CONTAINS = 'CONTAINS';
    const BEGINS = 'BEGINS';
    const ENDS = 'ENDS';
    const IN_SET = 'IN SET';

    const OPERATORS = [
        self::EQUAL,
    ];


    public $operator;

    public $column;

    public $value;


    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }


    /**
     *
     * @param string|int $key
     * @param array|string|int|float $item
     * @return static
     * @throws InvalidArgumentException
     */
    public static function fromShorthand($key, $item)
    {
        // Value-style conditions.
        if (is_numeric($key) and is_array($item)) {

            // Modified key-style condition.
            // => [OPERATOR, FIELD => VALUE]
            if (count($item) == 2) {
                /** @var string $operator */
                $operator = array_shift($item);

                $field = key($item);
                $value = $item[$field];

                return new PdbCondition([
                    'operator' => $operator,
                    'field' => $field,
                    'value' => $value,
                ]);
            }

            // Value-style condition.
            // [FIELD, OPERATOR, VALUE]
            if (count($item) == 3) {
                [$field, $operator, $value] = $item;

                return new PdbCondition([
                    'operator' => $operator,
                    'field' => $field,
                    'value' => $value,
                ]);
            }

            // TODO Not sure if this is helpful, but it means all the
            // validations are in one place.
            // throw new InvalidArgumentException('Conditions must have 2 or 3 items, not: ' . count($item));
            return new PdbCondition([]);
        }

        // Key-style conditions.
        // OPERATOR => VALUE
        else {
            return new PdbCondition([
                'operator' => PdbCondition::EQUAL,
                'field' => $key,
                'value' => $item,
            ]);
        }
    }


    /**
     *
     * @param array $clauses
     * @return static[]
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $clauses)
    {
        $conditions = [];
        foreach ($clauses as $key => $item) {
            $conditions[] = self::fromShorthand($key, $item);
        }
        return $conditions;
    }


    /**
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if ($this->operator === null and $this->column === null) {
            throw new InvalidArgumentException('Invalid/empty condition.');
        }

        if (!is_scalar($this->operator)) {
            throw new InvalidArgumentException('Operator unknown: ' . gettype($this->operator));
        }

        if (!isset(self::OPERATORS[$this->operator]) {
            throw new InvalidArgumentException('Operator must be scalar, not ' . $this->operator);
        }

        if (!is_scalar($this->column)) {
            throw new InvalidArgumentException('Column name must be scalar, not:' . gettype($this->column));
        }

        if (is_numeric($this->column) and !in_array($this->column, [0, 1])) {
            throw new InvalidArgumentException('Column name cannot be numeric (except for 0 or 1)');
        }

        Pdb::validateIdentifierExtended($this->column);
    }


    public function build(): string
    {
        $operator = $this->operator;
        $column = $this->column;
        $value = $this->value;

        switch ($operator) {
            case '=':
            case '<=':
            case '>=':
            case '<':
            case '>':
            case '!=':
            case '<>':
                if (!is_scalar($value)) {
                    $err = "Operator {$operator} needs a scalar value";
                    throw new InvalidArgumentException($err);
                }

                $where .= "{$column} {$operator} ?";
                $values[] = $value;
                break;

            case 'IS':
                if ($value === null) $value = 'NULL';
                if ($value )
                if ($value == 'NULL' or $value == 'NOT NULL') {
                    $where .= "{$column} {$operator} {$value}";
                } else {
                    $err = "Operator IS value must be NULL or NOT NULL";
                    throw new InvalidArgumentException($err);
                }
                break;

            case 'IS NOT':
                if ($value !== null) {
                    $err = "Operator IS NOT value must be NULL";
                    throw new InvalidArgumentException($err);
                }
                $where .= "{$column} {$operator} NULL";
                break;

            case 'BETWEEN':
                $err = "Operator BETWEEN value must be an array of two scalars";
                if (!is_array($value)) {
                    throw new InvalidArgumentException($err);
                } else if (count($value) != 2 or !is_scalar($value[0]) or !is_scalar($value[1])) {
                    throw new InvalidArgumentException($err);
                }
                $where .= "{$column} BETWEEN ? AND ?";
                $values[] = $value[0];
                $values[] = $value[1];
                break;

            case 'IN':
            case 'NOT IN';
                $err = "Operator {$operator} value must be an array of scalars";
                if (!is_array($value)) {
                    throw new InvalidArgumentException($err);
                } else {
                       foreach ($value as $idx => $v) {
                        if (!is_scalar($v)) {
                            throw new InvalidArgumentException($err . " (index {$idx})");
                        }
                    }
                }
                $where .= "{$column} {$operator} (" . rtrim(str_repeat('?, ', count($value)), ', ') . ')';
                foreach ($value as $v) {
                    $values[] = $v;
                }
                break;

            case 'CONTAINS':
                $where .= "{$column} LIKE CONCAT('%', ?, '%')";
                $values[] = PdbHelpers::likeEscape($value);
                break;

            case 'BEGINS':
                $where .= "{$column} LIKE CONCAT(?, '%')";
                $values[] = PdbHelpers::likeEscape($value);
                break;

            case 'ENDS':
                $where .= "{$column} LIKE CONCAT('%', ?)";
                $values[] = PdbHelpers::likeEscape($value);
                break;

            case 'IN SET':
                $where .= "FIND_IN_SET(?, {$column}) > 0";
                $values[] = PdbHelpers::likeEscape($value);
                break;

            default:
                $err = 'Operator not implemented: ' . $operator;
                throw new InvalidArgumentException($err);
        }
    }
}
