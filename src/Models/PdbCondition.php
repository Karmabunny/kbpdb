<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\PdbException;
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

    const COMPOUNDS = [
        'NOT',
        'OR',
        'AND',
        'XOR',
    ];

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


    /** @var string|null */
    public $operator;

    /** @var string|null */
    public $column;

    /** @var mixed */
    public $value;

    /** @var string|null Pdb::QUOTE_FIELD|QUOTE_VALUE|null */
    public $bind_type;

    /**
     * Create a condition.
     *
     * @param string|null $operator
     * @param string|null $column
     * @param mixed $value
     */
    public function __construct($operator, $column, $value, string $bind = null)
    {
        if ($operator) {
            $operator = trim(strtoupper($operator));
        }

        $this->operator = $operator;
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

            // key-style conditions + nested conditions.
            // :: [COLUMN => VALUE]
            // :: [AND|OR|XOR => CONDITION]
            if ($count == 1) {
                $column = key($item);
                $value = $item[$column];

                // This is when someone gives us a nested condition.
                // Like a: [['id' => 1]]
                return self::fromShorthand($column, $value);
            }

            // Modified key-style condition.
            // :: [OPERATOR, COLUMN => VALUE]
            if ($count == 2) {
                // TODO this isn't a safe assumption.
                /** @var string $operator */
                $operator = array_shift($item);
                $operator = trim(strtoupper($operator));

                $column = key($item);
                $value = $item[$column];

                // Shortcut for those not wrapping their conditions.
                if ($operator === 'NOT' and is_string($column)) {
                    $condition = self::fromShorthand($column, $value);
                    return new PdbCondition('NOT', null, [$condition]);
                }

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
            return new PdbCondition(null, null, $item);
        }

        // Key-style conditions + nested conditions.
        // :: COLUMN => VALUE
        // :: AND|OR|XOR|NOT => CONDITION
        if (is_string($key)) {
            $modifier = trim(strtoupper($key));

            // Support for nested conditions.
            if (
                is_array($item)
                and in_array($modifier, self::COMPOUNDS)
            ) {
                $conditions = self::fromArray($item);
                return new PdbCondition($modifier, null, $conditions);
            }

            // Regular key-style conditions.
            else {
                $operator = PdbCondition::EQUAL;
                if ($item === null) {
                    $operator = PdbCondition::IS;
                }

                return new PdbCondition($operator, $key, $item);
            }
        }

        $type = gettype($item);
        throw new InvalidArgumentException("Invalid condition: {$key} => {$type}");
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
        // String conditions have little-to-no validation.
        if ($this->operator === null) {
            return;
        }

        if (!is_scalar($this->operator)) {
            throw new InvalidArgumentException('Invalid operator: ' . gettype($this->operator));
        }

        if ($this->column !== null) {
            if (!in_array($this->operator, self::OPERATORS)) {
                throw new InvalidArgumentException("Unknown operator: '{$this->operator}'");
            }
        }
        else {
            if (!in_array($this->operator, self::COMPOUNDS)) {
                throw new InvalidArgumentException("Unknown compound operator: '{$this->operator}'");
            }
        }

        // Skip the rest for a nested condition.
        if ($this->column === null) return;

        if (!is_scalar($this->column)) {
            throw new InvalidArgumentException('Column name must be scalar, not: ' . gettype($this->column));
        }

        // For some reason this is ok?
        if (is_numeric($this->column) and !in_array($this->column, [0, 1])) {
            throw new InvalidArgumentException('Column name cannot be numeric (except for 0 or 1)');
        }

        Pdb::validateIdentifierExtended($this->column, true);

        // If the value is a field type, then we should do validation there too.
        if ($this->bind_type === Pdb::QUOTE_FIELD) {
            if (is_iterable($this->value)) {
                foreach ($this->value as $value) {
                    if (!is_scalar($value)) {
                        throw new InvalidArgumentException('Column array must be scalar');
                    }
                    Pdb::validateIdentifierExtended((string) $value, true);
                }
            }
            else {
                Pdb::validateIdentifierExtended((string) $this->value, true);
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
        // String conditions are a lawless world.
        if ($this->operator === null) {
            return $this->value;
        }

        // Nested conditions!
        if (
            $this->column === null and
            is_array($this->value)
        ) {
            $operator = $this->operator;
            $compound = '';

            // Special 'not' operator will perform a nested 'and'.
            if ($operator === 'NOT') {
                $operator = 'AND';
                $compound .= 'NOT ';
            }

            $compound .= '(';
            $first = true;

            foreach ($this->value as $condition) {
                if (!$first) {
                    $compound .= " {$operator} ";
                }

                $compound .= $condition->build($pdb, $values);
                $first = false;
            }

            $compound .= ')';
            return $compound;
        }

        $column = $this->column;

        // I'm not smart enough to auto-quote this.
        if (!preg_match(PdbHelpers::RE_FUNCTION, $column)) {
            $column = $pdb->quoteField($column);
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

                $bind = $this->bindValue($pdb, $this->value, $values);
                return "{$column} {$this->operator} {$bind}";

            case self::IS:
                $value = $this->value;

                if (is_string($value)) {
                    $value = strtoupper($value);

                    if (!in_array(strtoupper($value), ['NULL', 'NOT NULL'])) {
                        $value = false;
                    }
                }
                else if ($value === null) {
                    $value = 'NULL';
                }
                else {
                    $value = false;
                }

                if ($value === false) {
                    $err = "Operator IS value must be NULL or NOT NULL";
                    throw new InvalidArgumentException($err);
                }

                return "{$column} {$this->operator} {$value}";

            case self::IS_NOT:
                $value = $this->value;

                if (is_string($value)) {
                    $value = strtoupper($value);

                    if ($value !== 'NULL') {
                        $value = false;
                    }
                }
                else if ($value === null) {
                    $value = 'NULL';
                }
                else {
                    $value = false;
                }

                if ($value === false) {
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

                $low = $this->bindValue($pdb, $low, $values);
                $high = $this->bindValue($pdb, $high, $values);

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

                $binds = $this->bindValues($pdb, $items, $values);
                $binds = implode(', ', $binds);

                return "{$column} {$this->operator} ({$binds})";

            case self::CONTAINS:
                $bind = $this->bindLike($pdb, $this->value, $values);
                return "{$column} LIKE CONCAT('%', {$bind}, '%')";

            case self::BEGINS:
                $bind = $this->bindLike($pdb, $this->value, $values);
                return "{$column} LIKE CONCAT({$bind}, '%')";

            case self::ENDS:
                $bind = $this->bindLike($pdb, $this->value, $values);
                return "{$column} LIKE CONCAT('%', {$bind})";

            case self::IN_SET:
                $bind = $this->bindLike($pdb, $this->value, $values);
                return "FIND_IN_SET({$bind}, {$column}) > 0";

            default:
                $err = "Operator not implemented: {$this->operator}";
                throw new InvalidArgumentException($err);
        }
    }


    /**
     * Bind a value and return the binding token.
     *
     * The binding token will be either a placeholder or a quoted value - as
     * determined by the condition 'bind_type'.
     *
     * @param Pdb $pdb
     * @param mixed $value
     * @param mixed $params
     * @return string
     * @throws ConnectionException
     * @throws PdbException
     */
    private function bindValue(Pdb $pdb, $value, array &$params): string
    {
        if ($this->bind_type) {
            return $pdb->quote($value, $this->bind_type);
        }
        else {
            return $pdb->bindValue($value, $params);
        }
    }


    /**
     * Bind all values of an array.
     *
     * The binding tokens are returned as an array, either as placeholders or
     * quoted values - as determined by the condition 'bind_type'.
     *
     * @param Pdb $pdb
     * @param array $values
     * @param array $params
     * @return string[]
     * @throws ConnectionException
     * @throws PdbException
     */
    private function bindValues(Pdb $pdb, array $values, array &$params): array
    {
        if ($this->bind_type) {
            return $pdb->quoteAll($values, $this->bind_type);
        }
        else {
            $names = [];

            foreach ($values as $value) {
                $names[] = $pdb->bindValue($value, $params);
            }

            return $names;
        }
    }


    /**
     * Bind a value and wrap the binding token for a 'LIKE' condition.
     *
     * The binding token is either a placeholder or a quoted value - as
     * determined by the condition 'bind_type'.
     *
     * @param Pdb $pdb
     * @param string|null $value
     * @param array $params
     * @return string
     */
    private function bindLike(Pdb $pdb, $value, array &$params)
    {
        if ($this->bind_type) {
            if (!is_scalar($this->value)) {
                throw new InvalidArgumentException("Operator {$this->operator} value must be scalar");
            }

            $value = PdbHelpers::likeEscape($this->value);
            return $pdb->quote($value, $this->bind_type);
        }
        else {
            $value = PdbHelpers::likeEscape($this->value);
            return $pdb->bindValue($value, $params);
        }
    }
}
