<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2026 Karmabunny
 */

namespace karmabunny\pdb\Models;

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

    /**
     *
     * @param string $operator
     * @param array<string|int|float|null|self> $values
     * @return void
     */
    public function __construct(
        public string $operator,
        public array $values
    )
    {
    }


    /**
     *
     * @param mixed $value
     * @return static
     */
    public static function parse(mixed $value): static
    {
        if ($value === null) {
            return new self(
                PdbSimpleCondition::IS,
                [null],
            );
        }

        if (is_numeric($value)) {
            return new self(
                PdbSimpleCondition::EQUAL,
                [$value],
            );
        }

        if (is_string($value)) {
            if ($value == 'not null') {
                return new self(
                    PdbSimpleCondition::IS_NOT,
                    [null],
                );
            }

            if ($value == 'null') {
                return new self(
                    PdbSimpleCondition::IS,
                    [null],
                );
            }

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
     *
     * @param string $value
     * @return static
     */
    public static function parseString(string $value): static
    {
        $operator = PdbSimpleCondition::EQUAL;

        if (preg_match('/^([^,]+) /i', $value, $matches)) {
            $operator = strtoupper($matches[1]);
            $value = substr($value, strlen($matches[0]));
        }

        $value = self::splitArray($value);

        if (
            $operator === PdbSimpleCondition::EQUAL
            and count($value) > 1
        ) {
            $operator = PdbSimpleCondition::IN;
        }

        return new self(
            $operator,
            $value,
        );
    }


    /**
     *
     * @param array $value
     * @return static
     */
    public static function parseArray(array $value): static
    {
        if (empty($value)) {
            return new self(
                PdbSimpleCondition::EQUAL,
                [''],
            );
        }

        $first = reset($value);

        if (is_string($first)) {
            $first = strtoupper($first);

            if (in_array($first, PdbCompoundCondition::OPERATORS)) {
                array_shift($value);

                $values = [];

                foreach ($value as $item) {
                    if (is_string($item)) {
                        $item = self::parseString($item);
                    }
                    else if (is_array($item)) {
                        $item = self::parseArray($item);
                    }

                    $values[] = $item;
                }

                return new self($first, $values);
            }

            if (in_array($first, PdbSimpleCondition::OPERATORS)) {
                array_shift($value);
                return new self($first, $value);
            }
        }

        $values = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $item = self::parseString($item);
            }
            else if (is_array($item)) {
                $item = self::parseArray($item);
            }

            $values[] = $item;
        }

        return new self('OR', $values);
    }


    public function toSketch(): array
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


    public function toCondition(string $column): PdbConditionInterface
    {
        $operator = strtoupper($this->operator);

        if (in_array($operator, PdbCompoundCondition::OPERATORS)) {
            $conditions = [];

            foreach ($this->values as $value) {
                if ($value instanceof self) {
                    $value = $value->toCondition($column);
                }
                else if (is_array($value)) {
                    $value = new PdbSimpleCondition('IN', $column, $value);
                }
                else {
                    $value = new PdbSimpleCondition('=', $column, $value);
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


    public function toShorthand(string $column): array
    {
        $operator = strtoupper($this->operator);

        if (in_array($operator, PdbCompoundCondition::OPERATORS)) {
            $condition = [];

            foreach ($this->values as $value) {
                if (!$value instanceof self) {
                    continue;
                }

                $condition[$operator][] = $value->toShorthand($column);
            }

            return $condition;
        }
        else {
            $value = $this->values;

            if (count($value) == 1) {
                $value = $value[0];
            }

            return [$operator, $column => $value];
        }
    }


    /** @inheritdoc */
    public function toArray(): array
    {
        return $this->toSketch();
    }


    /** @inheritdoc */
    public function jsonSerialize(): array
    {
        return $this->toSketch();
    }


    /** @inheritdoc */
    public static function fromJson(array $json): static
    {
        return static::parseArray($json);
    }


    /**
     *
     * @param string $value
     * @return array
     */
    public static function splitArray(string $value): array
    {
        $value = preg_split('/(?<!\\\),/', $value);

        foreach ($value as &$item) {
            $item = trim($item);
            $item = str_replace('\,', ',', $item);
        }

        unset($item);

        $value = array_filter($value, fn($item) => $item !== '');
        $value = array_values($value);
        return $value;
    }


    /**
     * Convert a value into a sketch.
     *
     * @param mixed $value
     * @return array
     */
    public static function sketch(mixed $value): array
    {
        return self::parse($value)->toSketch();
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
}
