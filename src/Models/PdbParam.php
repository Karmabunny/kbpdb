<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2026 Karmabunny
 */

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use JsonSerializable;
use karmabunny\interfaces\ArrayableInterface;
use karmabunny\interfaces\JsonDeserializable;

/**
 * Parameter parser.
 *
 * This parser centres around the concept of a 'sketch' - this is sort of partial
 * condition that contains the operator and values but not the target column.
 *
 * Sketches can be parsed from strings, arrays, or other sketches. They inherit
 * the operators supported by {@see PdbSimpleCondition} and nested conditions
 * supported by {@see PdbCompoundCondition}.
 *
 * The aim to provide a natural language for writing conditions without
 * specifying the target column. This column is then later inserted when
 * converting into a full condition object.
 *
 * Usage:
 *
 * ```
 * $param = PdbParam::parse('> 20');
 * $condition = $param->toCondition('id');
 * [$where, $params] = $condition->build();
 *
 * // OR directly into a query builder:
 * $query = new PdbQuery($pdb);
 * $query->where(PdbParam::prepare('id', '> 20'));
 * $query->one();
 * ```
 *
 * Examples:
 *
 * | value              | sql                       |
 * |--------------------|---------------------------|
 * | `> 20`             | `id > ?`                  |
 * | `not null`         | `id IS NOT NULL`          |
 * | `1, 2, 3`          | `id IN (?, ?, ?)`         |
 * | `not in 1, 2, 3`   | `id NOT IN (?, ?, ?)`     |
 * | `< 10, > 20`       | `id < ? OR id > ?`        |
 * | `and >= 10, <= 20` | `id >= ? AND id <= ?`     |
 * | `not 10, 20`       | `NOT (id = ? AND id = ?)` |
 * | `begins abc`       | `id LIKE CONCAT(?, '%')`  |
 * | `between 1, 2`     | `id BETWEEN ? AND ?`      |
 * ```
 *
 * @package karmabunny\pdb
 */
class PdbParam implements ArrayableInterface, JsonSerializable, JsonDeserializable
{

    const OPERATORS_SIMPLE = PdbSimpleCondition::OPERATORS;

    const OPERATORS_COMPOUND = PdbCompoundCondition::OPERATORS;

    const OPERATORS_PREFIX = [
        ...self::OPERATORS_COMPOUND,
        PdbSimpleCondition::IN,
        PdbSimpleCondition::NOT_IN,
        PdbSimpleCondition::BETWEEN,
    ];


    /**
     *
     * @param string $operator
     * @param array $values
     * @return void
     */
    public function __construct(
        public string $operator,
        public array $values
    )
    {
    }


    /**
     * Convert a value/sketch into a condition.
     *
     * @param string $column
     * @param mixed $value
     * @return PdbConditionInterface
     */
    public static function prepare(string $column, mixed $value): PdbConditionInterface
    {
        return self::parse($value)->toCondition($column);
    }


    /**
     * Parse a value into a param.
     *
     * This accepts scalars, arrays - anything invalid raises an exception.
     *
     * @param mixed $value
     * @return self
     * @throws InvalidArgumentException
     */
    public static function parse(mixed $value): self
    {
        if ($value === null) {
            return new self(
                PdbSimpleCondition::IS,
                ['null'],
            );
        }

        // Shortcut.
        if (is_numeric($value)) {
            return new self(
                PdbSimpleCondition::EQUAL,
                [$value],
            );
        }

        if (is_string($value)) {
            return self::parseString($value);
        }

        if (is_array($value)) {
            return self::parseArray($value);
        }

        return new self(
            PdbSimpleCondition::EQUAL,
            [$value],
        );
    }


    /**
     * Parse a string expression.
     *
     * This can contain compound or simple conditions.
     *
     * @param string $value
     * @return self
     * @throws InvalidArgumentException
     */
    public static function parseString(string $value): self
    {
        if ($value === 'not null') {
            return new self(
                PdbSimpleCondition::IS_NOT,
                ['null'],
            );
        }

        if ($value === 'null') {
            return new self(
                PdbSimpleCondition::IS,
                ['null'],
            );
        }

        // After splitting it behaves just like an array.
        $values = self::split($value);
        return self::parseArray($values);
    }



    /**
     * Parse an array of values.
     *
     * This can contain simple or compound conditions.
     *
     * @param array $value
     * @return self
     * @throws InvalidArgumentException
     */
    public static function parseArray(array $value): self
    {
        if (empty($value)) {
            return new self(
                PdbSimpleCondition::EQUAL,
                [''],
            );
        }

        // We can stop here, we've got what we need.
        if ($expression = self::parseExpression($value)) {
            return $expression;
        }

        // Parse the first element as a potential compound operator.
        $compound = reset($value);

        // This is an explicit compound operator.
        if (self::isCompoundOperator($compound)) {
            $compound = strtoupper($compound);
            array_shift($value);
        }
        // Otherwise assume it's an OR/IN condition.
        else {
            $compound = null;
        }

        $values = [];
        $scalar = null;

        foreach ($value as $item) {
            $expression = self::parseExpression($item);

            // We're talking compounds we'll convert scalars too.
            if (!$expression and $compound and is_scalar($item)) {
                $expression = new self(PdbSimpleCondition::EQUAL, [$item]);
            }

            // Best not mix scalar and expressions for non-compound conditions.
            // So we set the 'scalar' flag on the first item and check that all
            // following items are matching.

            if ($expression) {
                if ($scalar === true) {
                    throw new InvalidArgumentException('Invalid expression: ' . json_encode($item));
                }

                $values[] = $expression;
                $scalar = false;
            }
            else if (is_scalar($item)) {
                if ($scalar === false) {
                    throw new \InvalidArgumentException('Invalid expression: ' . json_encode($item));
                }

                $values[] = $item;
                $scalar = true;
            }
            else {
                throw new \InvalidArgumentException('Invalid expression: ' . json_encode($item));
            }
        }

        // Special unwrapping for single items.
        if (count($values) === 1) {
            $value = reset($values);

            if ($value instanceof self) {
                return $value;
            }

            return new self(PdbSimpleCondition::EQUAL, $values);
        }

        // We condense scalars into a IN, which behaves like an OR.
        $operator = $compound ?? ($scalar ? PdbSimpleCondition::IN : PdbCompoundCondition::OR);

        return new self($operator, $values);
    }


    /**
     * Parse a simple condition, typically nested.
     *
     * This accepts a string like `'> 20'` or an array like `['>', '20']`.
     *
     * It cannot support compound or conditions with multiple values.
     *
     * @param string|array $value
     * @return null|self
     */
    public static function parseExpression(string|array $value): ?self
    {
        static $pattern = null;
        $pattern ??= self::buildPattern(self::OPERATORS_SIMPLE);

        // Array parsing is easy.
        if (is_array($value)) {
            $operator = reset($value);

            if ($operator and self::isSimpleOperator($operator)) {
                array_shift($value);
                return new self($operator, $value);
            }

            return null;
        }

        // Otherwise match the operator and trim it off.
        if (!preg_match($pattern, $value, $matches)) {
            return null;
        }

        $operator = strtoupper($matches[1]);

        $value = substr($value, strlen($matches[0]));
        return new self($operator, [$value]);
    }


    /**
     * Convert a param into a condition for a specified column name.
     *
     * @param string $column
     * @return PdbConditionInterface
     */
    public function toCondition(string $column): PdbConditionInterface
    {
        $operator = strtoupper($this->operator);

        if (in_array($operator, self::OPERATORS_COMPOUND)) {
            $conditions = [];

            foreach ($this->values as $value) {
                if ($value instanceof self) {
                    $value = $value->toCondition($column);
                }
                else if (is_array($value)) {
                    $value = new PdbSimpleCondition(PdbSimpleCondition::IN, $column, $value);
                }
                else {
                    $value = new PdbSimpleCondition(PdbSimpleCondition::EQUAL, $column, $value);
                }

                $conditions[] = $value;
            }

            return new PdbCompoundCondition($operator, $conditions);
        }
        else {
            $value = $this->values;

            if (count($value) == 1) {
                $value = $value[0];
            }

            return new PdbSimpleCondition($operator, $column, $value);
        }
    }


    /** @inheritdoc */
    public function toArray(): array
    {
        $operator = strtoupper($this->operator);

        $condition = [];
        $condition[] = $operator;

        foreach ($this->values as $value) {
            if ($value instanceof self) {
                $value = $value->toArray();
            }

            $condition[] = $value;
        }

        return $condition;
    }


    /** @inheritdoc */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }


    /** @inheritdoc */
    public static function fromJson(mixed $value): static
    {
        // @phpstan-ignore-next-line
        return self::parse($value);
    }


    /**
     * Is this a simple operator?
     *
     * @param mixed $operator
     * @return bool
     */
    public static function isSimpleOperator(mixed $operator): bool
    {
        static $operators = null;
        $operators ??= array_fill_keys(self::OPERATORS_SIMPLE, true);

        if (!is_string($operator)) {
            return false;
        }

        $operator = strtoupper($operator);
        return isset($operators[$operator]);
    }


    /**
     * Is this a compound operator?
     *
     * @param mixed $operator
     * @return bool
     */
    public static function isCompoundOperator(mixed $operator): bool
    {
        static $operators = null;
        $operators ??= array_fill_keys(self::OPERATORS_COMPOUND, true);

        if (!is_string($operator)) {
            return false;
        }

        $operator = strtoupper($operator);
        return isset($operators[$operator]);
    }


    /**
     * Build a prefix pattern for a list of operators.
     *
     * @param array $operators
     * @return string
     */
    public static function buildPattern(array $operators): string
    {
        usort($operators, fn($a, $b) => strlen($b) - strlen($a));
        $operators = implode('|', $operators);
        return "/^($operators)\s+/i";
    }


    /**
     * Split a string into an array of values.
     *
     * Prefix operators are preserved as the first element.
     *
     * @param string $value
     * @return array
     */
    public static function split(string $value): array
    {
        static $pattern = null;
        $pattern ??= self::buildPattern(self::OPERATORS_PREFIX);

        // Split on commas, unescaped.
        $value = preg_split('/(?<!\\\),/', $value);

        $items = [];

        foreach ($value as $item) {
            $item = trim($item);
            $item = str_replace('\,', ',', $item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        if (count($items) < 2) {
            return $items;
        }

        // Does the first item have a prefix operator?
        if (preg_match($pattern, $items[0], $matches)) {
            $items[0] = substr($items[0], strlen($matches[0]));
            array_unshift($items, strtoupper($matches[1]));
        }

        return $items;
    }
}
