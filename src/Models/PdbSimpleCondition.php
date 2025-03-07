<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use karmabunny\pdb\Exceptions\InvalidConditionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbHelpers;
use karmabunny\pdb\PdbQueryInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbSimpleCondition implements PdbConditionInterface
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
    const LIKE = 'LIKE';
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
        self::LIKE,
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

    /**
     * This controls the bind method of the 'value'.
     *
     * - Given `null` this uses parametric binding. This is the safest method.
     *
     * - Use the `FIELD` type to treat the 'value' as a column/table type.
     * This will apply the appropriate quoting method.
     *
     * - The `VALUE` type will use the PDO driver's 'quote()' method.
     * However this is still not as safe as using natural bindings.
     *
     * @var string|null Pdb::QUOTE_FIELD|QUOTE_VALUE|null
     **/
    public $bind_type;

    /**
     * Create a condition.
     *
     * @param string $operator
     * @param string $column
     * @param mixed $value
     * @param string|null $bind
     */
    public function __construct(string $operator, string $column, $value, ?string $bind = null)
    {
        $this->operator = trim(strtoupper($operator));
        $this->column = $column;
        $this->value = $value;
        $this->bind_type = $bind;
    }


    /** @inheritdoc */
    public function validate()
    {
        if (!is_scalar($this->operator)) {
            $message = 'Invalid operator: ' . gettype($this->operator);
                throw (new InvalidConditionException($message))
                    ->withCondition($this);
        }

        if ($this->column !== null) {
            if (!in_array($this->operator, self::OPERATORS)) {
                $message = "Unknown operator: '{$this->operator}'";
                throw (new InvalidConditionException($message))
                    ->withCondition($this);
            }
        }

        if (!is_scalar($this->column)) {
            $message = 'Column name must be scalar';
            throw (new InvalidConditionException($message))
                ->withActual($this->column)
                ->withCondition($this);
        }

        // For some reason this is ok?
        if (is_numeric($this->column) and !in_array($this->column, [0, 1])) {
            $message = 'Column name cannot be numeric (except for 0 or 1)';
            throw (new InvalidConditionException($message))
                ->withActual($this->column)
                ->withCondition($this);
        }

        Pdb::validateIdentifierExtended($this->column, true);

        // Validate nested conditions.
        if ($this->value instanceof PdbConditionInterface) {

            // But first check what operator we've paired it with.
            switch ($this->operator) {
                case self::IS:
                case self::IS_NOT:
                case self::BETWEEN:
                    $message = "Nested conditions cannot be used with operator: {$this->operator}";
                    throw (new InvalidConditionException($message))
                        ->withCondition($this);
            }

            $this->value->validate();
        }

        // Pre-validate the query, we'll turn of validation later to save effort.
        if ($this->value instanceof PdbQueryInterface) {
            switch ($this->operator) {
                case self::IS:
                case self::IS_NOT:
                case self::BETWEEN:
                case self::CONTAINS:
                case self::BEGINS:
                case self::ENDS:
                case self::IN_SET:
                    $message = "Nested conditions cannot be used with operator: {$this->operator}";
                    throw (new InvalidConditionException($message))
                        ->withCondition($this);
            }

            // TODO This is still a tad expensive because we're building the
            // condition sets twice.
            $this->value->validate();
        }

        // If the value is a field type, then we should do validation there too.
        if ($this->bind_type === Pdb::QUOTE_FIELD) {
            if (
                is_iterable($this->value)
                and !($this->value instanceof PdbConditionInterface)
            ) {
                foreach ($this->value as $index => $value) {
                    if (!is_scalar($value)) {
                        $message = "Column array must be scalar (index {$index})";
                        throw (new InvalidConditionException($message))
                            ->withActual($value)
                            ->withCondition($this);
                    }
                    Pdb::validateIdentifierExtended((string) $value, true);
                }
            }
            else if (is_scalar($this->value)) {
                Pdb::validateIdentifierExtended((string) $this->value, true);
            }
            else if ($this->value instanceof PdbConditionInterface) {
                // OK, idk this just reads better.
            }
            else {
                $message = 'Column must be scalar or array of scalars';
                throw (new InvalidConditionException($message))
                    ->withActual($this->value)
                    ->withCondition($this);
            }
        }
    }


    /** @inheritdoc */
    public function build(Pdb $pdb, array &$values): string
    {
        $column = $this->column;
        $value = $this->value;

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

                // This is a nested condition, we can't force the bind mode so
                // we just go with whatever it does.
                if ($value instanceof PdbConditionInterface) {
                    $bind = $value->build($pdb, $values);
                }
                // Some nested queries.
                else if ($value instanceof PdbQueryInterface) {
                    [$bind, $subValues] = $value->build(false);
                    $values = array_merge($values, $subValues);
                }
                // Gonna 'bind' this one manually. Doesn't feel great.
                else if ($this->bind_type) {
                    if ($this->bind_type === Pdb::QUOTE_VALUE) {
                        $value = $pdb->format($value);
                    }

                    if (!is_scalar($value)) {
                        $message = "Operator {$this->operator} needs a scalar value";
                        throw (new InvalidConditionException($message))
                            ->withActual($this->value)
                            ->withCondition($this);
                    }

                    $bind = $pdb->quote($value, $this->bind_type);
                }
                // Natural bindings. Good.
                else {
                    $values[] = $this->value;
                    $bind = '?';
                }

                return "{$column} {$this->operator} {$bind}";

            case self::IS:
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
                    $message = "Operator IS value must be NULL or NOT NULL";
                    throw (new InvalidConditionException($message))
                        ->withActual($this->value)
                        ->withCondition($this);
                }

                return "{$column} {$this->operator} {$value}";

            case self::IS_NOT:
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
                    $message = "Operator IS NOT value must be NULL";
                    throw (new InvalidConditionException($message))
                        ->withActual($this->value)
                        ->withCondition($this);
                }

                return "{$column} {$this->operator} NULL";

            case self::BETWEEN:
                if (!is_array($value)) {
                    $message = "Operator BETWEEN value must be an array of two scalars";
                    throw (new InvalidConditionException($message))
                        ->withActual($this->value)
                        ->withCondition($this);
                }

                if (count($value) != 2) {
                    $message = "Operator BETWEEN value must be an array of two scalars";
                    throw (new InvalidConditionException($message))
                        ->withActual(count($this->value))
                        ->withCondition($this);
                }

                [$low, $high] = array_values($value);

                if (!$this->bind_type) {
                    $values[] = $low;
                    $values[] = $high;
                    $high = $low = '?';
                }
                else {
                    if ($this->bind_type === Pdb::QUOTE_VALUE) {
                        $high = $pdb->format($high);
                        $low = $pdb->format($low);
                    }

                    if (!is_scalar($low) or !is_scalar($high)) {
                        $message = "Operator BETWEEN value must be an array of two scalars";
                        $actual = gettype($low) . ' and ' . gettype($high);
                        throw (new InvalidConditionException($message))
                            ->withActual($actual)
                            ->withCondition($this);
                    }

                    $low = $pdb->quoteValue($low);
                    $high = $pdb->quoteValue($high);
                }

                return "{$column} BETWEEN {$low} AND {$high}";

            case self::IN:
            case self::NOT_IN:
                if (
                    !is_array($value)
                    and !$value instanceof PdbConditionInterface
                    and !$value instanceof PdbQueryInterface
                ) {
                    $message = "Operator {$this->operator} value must be an array of scalars";

                    throw (new InvalidConditionException($message))
                        ->withActual($this->value)
                        ->withCondition($this);
                }

                if (empty($value)) return '';

                if ($value instanceof PdbConditionInterface) {
                    $binds = $value->build($pdb, $values);
                }
                else if ($value instanceof PdbQueryInterface) {
                    [$binds, $subValues] = $value->build(false);
                    $values = array_merge($values, $subValues);
                }
                else if (!$this->bind_type) {
                    $binds = PdbHelpers::bindPlaceholders(count($value));

                    foreach ($value as $item) {
                        $values[] = $item;
                    }
                }
                else {
                    foreach ($value as $index => &$item) {
                        if ($this->bind_type === Pdb::QUOTE_VALUE) {
                            $item = $pdb->format($item);
                        }

                        if (!is_scalar($item)) {
                            $message = "Operator {$this->operator} value must be an array of scalars";
                            throw (new InvalidConditionException($message))
                                ->withActual(gettype($this->value) . " (index {$index})")
                                ->withCondition($this);
                        }

                        $item = $pdb->quote($item, $this->bind_type);
                    }
                    unset($item);

                    $binds = implode(', ', $value);
                }

                return "{$column} {$this->operator} ({$binds})";

            case self::LIKE:
                $bind = $this->quoteLike($pdb, $values);
                return "{$column} LIKE {$bind}";

            case self::CONTAINS:
                $bind = $this->quoteLike($pdb, $values);
                return "{$column} LIKE CONCAT('%', {$bind}, '%')";

            case self::BEGINS:
                $bind = $this->quoteLike($pdb, $values);
                return "{$column} LIKE CONCAT({$bind}, '%')";

            case self::ENDS:
                $bind = $this->quoteLike($pdb, $values);
                return "{$column} LIKE CONCAT('%', {$bind})";

            case self::IN_SET:
                $bind = $this->quoteLike($pdb, $values);
                return "FIND_IN_SET({$bind}, {$column}) > 0";

            default:
                $message = "Operator not implemented: {$this->operator}";
                throw (new InvalidConditionException($message))
                    ->withCondition($this);
        }
    }


    private function quoteLike(Pdb $pdb, array &$values)
    {
        $value = $this->value;

        if ($value instanceof PdbConditionInterface) {
            return $value->build($pdb, $values);
        }
        else if (!$this->bind_type) {
            $values[] = PdbHelpers::likeEscape($value);
            return '?';
        }
        else {
            if ($this->bind_type === Pdb::QUOTE_VALUE) {
                $value = $pdb->format($value);
            }

            if (!is_scalar($value)) {
                $message = "Operator {$this->operator} value must be scalar";
                throw (new InvalidConditionException($message))
                    ->withActual($this->value)
                    ->withCondition($this);
            }

            $value = PdbHelpers::likeEscape($value);
            $value = $pdb->quote($value, $this->bind_type);
            return $value;
        }
    }


    /** @inheritdoc */
    public function getPreviewSql(): string
    {
        // TODO generate preview for nested conditions + queries.

        switch ($this->operator) {
            case self::EQUAL:
            case self::NOT_EQUAL;
            case self::NOT_EQUAL_ALT;
            case self::GREATER_THAN_EQUAL:
            case self::LESS_THAN_EQUAL:
            case self::LESS_THAN:
            case self::GREATER_THAN:
            case self::IS:
            case self::IS_NOT:
                return "{$this->column} {$this->operator} ?";

            case self::BETWEEN:
                return "{$this->column} BETWEEN ? AND ?";

            case self::IN:
            case self::NOT_IN:
                return "{$this->column} {$this->operator} (...)";

            case self::CONTAINS:
                return "{$this->column} LIKE CONCAT('%', ?, '%')";

            case self::BEGINS:
                return "{$this->column} LIKE CONCAT(?, '%')";

            case self::ENDS:
                return "{$this->column} LIKE CONCAT('%', ?)";

            case self::IN_SET:
                return "FIND_IN_SET(?, {$this->column}) > 0";
        }

        return '';
    }


    /** @inheritdoc */
    public function __toString()
    {
        $preview = $this->getPreviewSql();

        if ($preview) {
            return $preview;
        }

        $type = gettype($this->value);
        return "[{$this->column}, {$this->operator}, {$type}]";
    }
}
