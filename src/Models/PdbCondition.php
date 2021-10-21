<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbHelpers;
use PDOException;

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
        self::LESS_THAN_EQUAL,
        self::GREATER_THAN_EQUAL,
        self::LESS_THAN,
        self::GREATER_THAN,
        self::NOT_EQUAL,
        self::NOT_EQUAL_ALT,
        self::IS,
        self::IS_NOT,
        self::BETWEEN,
        self::IN,
        self::NOT_IN,
        self::CONTAINS,
        self::BEGINS,
        self::ENDS,
        self::IN_SET,
    ];


    /** @var string */
    public $operator;

    /** @var string */
    public $column;

    /** @var mixed */
    public $value;

    /** @var string|null Pdb::QUOTE_FIELD|QUOTE_VALUE|null */
    public $bind_type;

    /**
     * Create a condition.
     *
     * @param string $operator
     * @param string $column
     * @param mixed $value
     */
    public function __construct($operator, $column, $value, string $bind = null)
    {
        $this->operator = strtoupper($operator);
        $this->column = $column;
        $this->value = $value;
        $this->bind_type = $bind;
    }


    /**
     * Create a condition object from a shorthand array.
     *
     * A 'shorthand' array is one 3 forms:
     * 1. [column, operator, value]
     * 2. [operator, column => value]
     * 3. [column => value]
     *
     * The third is only for equality or IS NULL conditions.
     *
     * @param string|int $key
     * @param PdbCondition|array|string|int|float|null $item
     * @return PdbCondition
     * @throws InvalidArgumentException
     */
    public static function fromShorthand($key, $item)
    {
        // Pass-through.
        if ($item instanceof static) {
            return clone $item;
        }

        // Value-style conditions.
        if (is_numeric($key) and is_array($item)) {
            $count = count($item);

            // key-style condition.
            // :: [COLUMN => VALUE]
            if ($count == 1) {
                $column = key($item);
                $value = $item[$column];

                $operator = PdbCondition::EQUAL;
                if ($value === null) {
                    $operator = PdbCondition::IS;
                }

                return new PdbCondition($operator, $column, $value);
            }

            // Modified key-style condition.
            // :: [OPERATOR, COLUMN => VALUE]
            if ($count == 2) {
                /** @var string $operator */
                $operator = array_shift($item);

                $column = key($item);
                $value = $item[$column];

                return new PdbCondition($operator, $column, $value);
            }

            // Value-style condition.
            // :: [FIELD, OPERATOR, VALUE]
            if ($count == 3) {
                [$column, $operator, $value] = $item;

                return new PdbCondition($operator, $column, $value);
            }

            throw new InvalidArgumentException('Conditions must have 1, 2 or 3 items, not: ' . $count);
        }

        // String-style conditions.
        // :: 'operator = value'
        // - Particularly useful for joins.
        // - NOT to be used for unescaped values. Stick to array or key style.
        // - Doesn't support non-scalar values.
        if (is_numeric($key) and is_string($item)) {
            $matches = [];
            if (!preg_match('/^([a-z0-9_\.\']+)\s+([=!<>]+|IS|IS NOT)\s+(.+?)$/i', $item, $matches)) {
                throw new InvalidArgumentException("Invalid string condition: [{$item}]");
            }

            [$_, $left, $operator, $right] = $matches;
            $value = trim($right, '\'');

            // Perform ? binding if the right field is wrapped in ''.
            // Otherwise treat it like a column/table will be `` escaped.
            if ($value != $right) {
                $bind = null;
            }
            else {
                $bind = Pdb::QUOTE_FIELD;
            }

            return new PdbCondition($operator, $left, $value, $bind);
        }

        // Key-style conditions.
        // :: OPERATOR => VALUE
        if (is_string($key)) {
            $operator = PdbCondition::EQUAL;

            if ($item === null) {
                $operator = PdbCondition::IS;
            }

            return new PdbCondition($operator, $key, $item);
        }

        throw new InvalidArgumentException('Invalid condition');
    }


    /**
     * Create a list of conditions objects from a list of configuration
     * shorthand arrays.
     *
     * @param array $clauses
     * @return PdbCondition[]
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $clauses)
    {
        $conditions = [];
        foreach ($clauses as $key => $item) {
            $item = self::fromShorthand($key, $item);
            $item->validate();
            $conditions[] = $item;
        }
        return $conditions;
    }


    /**
     * Validate this condition.
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

        if (!in_array($this->operator, self::OPERATORS)) {
            throw new InvalidArgumentException('Operator unknown: ' . $this->operator);
        }

        if (!is_scalar($this->column)) {
            throw new InvalidArgumentException('Column name must be scalar, not:' . gettype($this->column));
        }

        if (is_numeric($this->column) and !in_array($this->column, [0, 1])) {
            throw new InvalidArgumentException('Column name cannot be numeric (except for 0 or 1)');
        }

        Pdb::validateIdentifierExtended($this->column);

        if ($this->bind_type === Pdb::QUOTE_FIELD) {
            if (is_iterable($this->value)) {
                foreach ($this->value as $value) {
                    if (!is_scalar($value)) {
                        throw new InvalidArgumentException('Column array must be scalar');
                    }
                    Pdb::validateIdentifierExtended((string) $value);
                }
            }
            else {
                Pdb::validateIdentifierExtended((string) $this->value);
            }
        }
    }


    /**
     * Build an appropriate SQL clause for this condition.
     *
     * The values will be created as ? and added to the $values parameter to
     * permit one to bind the values later in an safe manner.
     *
     * @param Pdb $pdb
     * @param array $values
     * @return string
     * @throws PDOException
     * @throws InvalidArgumentException
     */
    public function build(Pdb $pdb, array &$values): string
    {
        $column = $pdb->quoteField($this->column);

        if ($this->bind_type and is_scalar($this->value)) {
            $bind = $pdb->quote($this->value, $this->bind_type);
        }
        else {
            $bind = '?';
        }

        switch ($this->operator) {
            case self::EQUAL:
            case self::NOT_EQUAL;
            case self::NOT_EQUAL_ALT;
            case self::GREATER_THAN_EQUAL:
            case self::LESS_THAN_EQUAL:
            case self::LESS_THAN:
            case self::GREATER_THAN:
                if (!is_scalar($this->value)) {
                    $err = "Operator {$this->operator} needs a scalar value";
                    throw new InvalidArgumentException($err);
                }

                if (!$this->bind_type) $values[] = $this->value;
                return "{$column} {$this->operator} {$bind}";

            case self::IS:
                $value = $this->value;
                if ($value === null) $value = 'NULL';

                if (!in_array($value, ['NULL', 'NOT NULL'])) {
                    $err = "Operator IS value must be NULL or NOT NULL";
                    throw new InvalidArgumentException($err);
                }

                return "{$column} {$this->operator} {$value}";

            case self::IS_NOT:
                if ($this->value !== null) {
                    $err = "Operator IS NOT value must be NULL";
                    throw new InvalidArgumentException($err);
                }
                return "{$column} {$this->operator} NULL";

            case self::BETWEEN:
                $err = "Operator BETWEEN value must be an array of two scalars";

                if (!is_array($this->value) or count($this->value) != 2) {
                    throw new InvalidArgumentException($err);
                }

                [$low, $high] = array_values($this->value);

                if (!is_scalar($low) or !is_scalar($high)) {
                    throw new InvalidArgumentException($err);
                }

                if (!$this->bind_type) {
                    $values[] = $low;
                    $values[] = $high;
                    $high = $low = '?';
                }
                else {
                    $low = $pdb->quoteValue($low);
                    $high = $pdb->quoteValue($high);
                }

                return "{$column} BETWEEN {$low} AND {$high}";

            case self::IN:
            case self::NOT_IN:
                $items = $this->value;
                $err = "Operator {$this->operator} value must be an array of scalars";

                if (!is_array($items)) {
                    throw new InvalidArgumentException($err);
                }

                foreach ($items as $index => $item) {
                    if (!is_scalar($item)) {
                        throw new InvalidArgumentException($err . " (index {$index})");
                    }
                }

                if (empty($items)) return '';

                if (!$this->bind_type) {
                    $binds = PdbHelpers::bindPlaceholders(count($items));

                    foreach ($items as $item) {
                        $values[] = $item;
                    }
                }
                else {
                    $items = $pdb->quoteAll($items, $this->bind_type);
                    $binds = implode(', ', $items);
                }

                return "{$column} {$this->operator} ({$binds})";

            case self::CONTAINS:
                $bind = $this->likeEscape($pdb, $values);
                return "{$column} LIKE CONCAT('%', {$bind}, '%')";

            case self::BEGINS:
                $bind = $this->likeEscape($pdb, $values);
                return "{$column} LIKE CONCAT({$bind}, '%')";

            case self::ENDS:
                $bind = $this->likeEscape($pdb, $values);
                return "{$column} LIKE CONCAT('%', {$bind})";

            case self::IN_SET:
                $bind = $this->likeEscape($pdb, $values);
                return "FIND_IN_SET({$bind}, {$column}) > 0";

            default:
                $err = "Operator not implemented: {$this->operator}";
                throw new InvalidArgumentException($err);
        }
    }


    private function likeEscape(Pdb $pdb, array &$values)
    {
        if (!$this->bind_type) {
            $values[] = PdbHelpers::likeEscape($this->value);
            return '?';
        }
        else {
            if (!is_scalar($this->value)) {
                throw new InvalidArgumentException("Operator {$this->operator} value must be scalar");
            }

            $value = PdbHelpers::likeEscape($this->value);
            $value = $pdb->quote($value, $this->bind_type);
            return $value;
        }
    }
}
