<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2026 Karmabunny
 */

namespace karmabunny\pdb\Models;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;
use karmabunny\interfaces\ArrayableInterface;
use karmabunny\interfaces\JsonDeserializable;
use karmabunny\kb\Configure;
use karmabunny\kb\Time;

/**
 * Parameter parser.
 *
 * This parses a form of query condition that doesn't include the target column.
 *
 * The parser accepts strings, arrays, and operators from
 * {@see PdbSimpleCondition} and {@see PdbCompoundCondition}.
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
 * // For most use-cases, use the prepare() shorthand:
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
 *
 * The parser supports relative dates:
 *
 * ```
 * PdbParam::prepare('dateCreated', '> today', ['relativeDates']);
 * // => WHERE dateCreated > ?
 * // => [new DateTime('today')]
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
        public array $values,
    )
    {
    }


    /**
     * Build a new param.
     *
     * @param string $operator
     * @param array $values
     * @param array $config
     * @return PdbParam
     */
    public static function build(string $operator, array $values, array $config = []): self
    {
        self::processValues($values, $config);
        return new self($operator, $values);
    }


    /**
     * Convert a value/sketch into a condition.
     *
     * @param string $column
     * @param mixed $value
     * @param array $config
     * @return PdbConditionInterface
     */
    public static function prepare(string $column, mixed $value, array $config = []): PdbConditionInterface
    {
        return self::parse($value, $config)->toCondition($column);
    }


    /**
     * Parse a value into a param.
     *
     * This accepts scalars, arrays - anything invalid raises an exception.
     *
     * @param mixed $value
     * @param array $config
     * @return self
     * @throws InvalidArgumentException
     */
    public static function parse(mixed $value, array $config = []): self
    {
        if ($value === null) {
            return self::build(
                PdbSimpleCondition::IS,
                ['null'],
                $config,
            );
        }

        // Shortcut.
        if (is_numeric($value)) {
            return self::build(
                PdbSimpleCondition::EQUAL,
                [$value],
                $config,
            );
        }

        if (is_string($value)) {
            return self::parseString($value, $config);
        }

        if (is_array($value)) {
            return self::parseArray($value, $config);
        }

        return self::build(
            PdbSimpleCondition::EQUAL,
            [$value],
            $config,
        );
    }


    /**
     * Parse a string expression.
     *
     * This can contain compound or simple conditions.
     *
     * @param string $value
     * @param array $config
     * @return self
     * @throws InvalidArgumentException
     */
    public static function parseString(string $value, array $config = []): self
    {
        if ($value === 'not null') {
            return self::build(
                PdbSimpleCondition::IS_NOT,
                ['null'],
                $config,
            );
        }

        if ($value === 'null') {
            return self::build(
                PdbSimpleCondition::IS,
                ['null'],
                $config,
            );
        }

        // After splitting it behaves just like an array.
        $values = self::split($value);
        return self::parseArray($values, $config);
    }



    /**
     * Parse an array of values.
     *
     * This can contain simple or compound conditions.
     *
     * @param array $value
     * @param array $config
     * @return self
     * @throws InvalidArgumentException
     */
    public static function parseArray(array $value, array $config = []): self
    {
        if (empty($value)) {
            return self::build(
                PdbSimpleCondition::EQUAL,
                [''],
                $config,
            );
        }

        // We can stop here, we've got what we need.
        if ($expression = self::parseExpression($value, $config)) {
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
            $expression = self::parseExpression($item, $config);

            // We're talking compounds we'll convert scalars too.
            if (!$expression and $compound and is_scalar($item)) {
                $expression = self::build(PdbSimpleCondition::EQUAL, [$item], $config);
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

            return self::build(PdbSimpleCondition::EQUAL, $values, $config);
        }

        // We condense scalars into a IN, which behaves like an OR.
        $operator = $compound ?? ($scalar ? PdbSimpleCondition::IN : PdbCompoundCondition::OR);

        return self::build($operator, $values, $config);
    }


    /**
     * Parse a simple condition, typically nested.
     *
     * This accepts a string like `'> 20'` or an array like `['>', '20']`.
     *
     * It cannot support compound or conditions with multiple values.
     *
     * @param string|array $value
     * @param array $config
     * @return null|self
     */
    public static function parseExpression(string|array $value, array $config = []): ?self
    {
        static $pattern = null;
        $pattern ??= self::buildPattern(self::OPERATORS_SIMPLE);

        // Array parsing is easy.
        if (is_array($value)) {
            $operator = reset($value);

            if ($operator and self::isSimpleOperator($operator)) {
                array_shift($value);
                return self::build($operator, $value, $config);
            }

            return null;
        }

        // Otherwise match the operator and trim it off.
        if (!preg_match($pattern, $value, $matches)) {
            return null;
        }

        $operator = strtoupper($matches[1]);

        $value = substr($value, strlen($matches[0]));
        return self::build($operator, [$value], $config);
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
            else if ($value instanceof DateTimeInterface) {
                $value = json_decode(json_encode($value), true);
                unset($value['timezone_type']);
            }
            else if (is_object($value)) {
                $value = [get_class($value) => (array) $value];
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


    /**
     * Process values in an array.
     *
     * @param array $values
     * @param array $config
     * @return void
     */
    public static function processValues(array &$values, array $config): void
    {
        $processDates = $config['relativeDates'] ?? in_array('relativeDates', $config);

        foreach ($values as &$value) {
            if (is_array($value)) {
                if (isset($value['date'])) {
                    $tz = new DateTimeZone($value['timezone'] ?? 'UTC');
                    $value = new DateTimeImmutable($value['date'], $tz);
                    continue;
                }

                if (
                    is_string($class = key($value))
                    and class_exists($class)
                ) {
                    $value = reset($value) ?: [];
                    $value = Configure::create($class, $value);
                    continue;
                }
            }

            if ($processDates) {
                if (
                    is_string($value)
                    and Time::hasRelativeKeywords($value)
                ) {
                    $value = Time::parse($value);
                    continue;
                }
            }
        }
    }
}
