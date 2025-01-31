<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use karmabunny\pdb\Exceptions\InvalidConditionException;
use karmabunny\pdb\Pdb;
use PDOException;

/**
 * This is a helper for building conditions from the array syntax.
 *
 * Although you _can_ extend this to implement new conditions, best not.
 * Use {@see PdbConditionInterface} instead.
 *
 * @package karmabunny\pdb
 */
abstract class PdbCondition implements PdbConditionInterface
{

    /**
     * Create a condition object from a shorthand array.
     *
     * A 'shorthand' array is one 3 forms:
     * 1. [column, operator, value]
     * 2. [operator, column => value]
     * 3. [column => value]
     *
     * The third is only for equality, IN, IS NULL conditions.
     *
     * @param string|int|null $key
     * @param PdbConditionInterface|array|string|int|float|null $item
     * @return PdbConditionInterface
     * @throws InvalidConditionException
     */
    public static function fromShorthand($key, $item)
    {
        // Pass-through.
        if ($item instanceof PdbConditionInterface) {
            return clone $item;
        }

        // Value-style conditions.
        if (
            ($key === null or is_numeric($key))
            and is_array($item)
        ) {
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
                $operator = array_shift($item);

                if (!is_string($operator)) {
                    $type = gettype($operator);
                    throw new InvalidConditionException("Operator must be a string, got: {$type} [array]");
                }

                $operator = trim(strtoupper($operator));

                $column = key($item);
                $value = $item[$column];

                // Shortcut for those not wrapping their conditions.
                if ($operator === 'NOT' and is_string($column)) {
                    $condition = self::fromShorthand($column, $value);
                    return new PdbCompoundCondition('NOT', [$condition]);
                }

                return new PdbSimpleCondition($operator, $column, $value);
            }

            // Value-style condition.
            // :: [FIELD, OPERATOR, VALUE]
            if ($count == 3) {
                [$column, $operator, $value] = $item;
                return new PdbSimpleCondition($operator, $column, $value);
            }

            throw new InvalidConditionException('Conditions must have 1, 2 or 3 items, not: ' . $count);
        }

        // String-style conditions.
        // :: 'operator = value'
        // We ask that users explicitly encode a PdbRawCondition object to
        // declare their intent. Exception can happen further up the call chain.
        //  e.g. in PdbQuery::join().
        if (is_numeric($key) and is_string($item)) {
            throw new InvalidConditionException('Invalid string condition');
        }

        // Key-style conditions + nested conditions.
        // :: COLUMN => VALUE
        // :: AND|OR|XOR|NOT => CONDITION
        if (is_string($key)) {
            $modifier = trim(strtoupper($key));

            if (is_array($item)) {
                // Support for nested conditions.
                if (in_array($modifier, PdbCompoundCondition::OPERATORS)) {
                    $conditions = self::fromArray($item);
                    return new PdbCompoundCondition($modifier, $conditions);
                }
                else {
                    return new PdbSimpleCondition(PdbSimpleCondition::IN, $key, $item);
                }
            }
            // Regular key-style conditions.
            else {
                $operator = PdbSimpleCondition::EQUAL;
                if ($item === null) {
                    $operator = PdbSimpleCondition::IS;
                }

                return new PdbSimpleCondition($operator, $key, $item);
            }
        }

        $type = gettype($item);
        throw new InvalidConditionException("Invalid condition: {$key} => {$type}");
    }


    /**
     * Create a list of conditions objects from a list of configuration
     * shorthand arrays.
     *
     * @param array $clauses
     * @param bool $validate
     * @return PdbConditionInterface[]
     * @throws InvalidConditionException
     */
    public static function fromArray(array $clauses, bool $validate = true)
    {
        $conditions = [];
        foreach ($clauses as $key => $item) {
            $item = self::fromShorthand($key, $item);

            if ($validate) {
                $item->validate();
            }

            $conditions[] = $item;
        }
        return $conditions;
    }


    /**
     * Build a set of conditions into SQL.
     *
     * @see Pdb::buildClause()
     *
     * @param Pdb $pdb
     * @param array $conditions
     * @param array &$values
     * @param string $combine
     * @param bool $validate
     * @return string
     * @throws InvalidArgumentException
     * @throws InvalidConditionException
     * @throws PDOException
     */
    public static function buildClause(Pdb $pdb, array $conditions, array &$values, $combine = 'AND', $validate = true)
    {
        if ($validate and !in_array($combine, PdbCompoundCondition::COMPOUNDS)) {
            $compounds = implode(', ', PdbCompoundCondition::COMPOUNDS);
            throw new InvalidArgumentException('Combine parameter must be one of: ' . $compounds);
        }

        $conditions = self::fromArray($conditions, $validate);
        $combine = " {$combine} ";
        $where = '';

        foreach ($conditions as $condition) {
            $clause = $condition->build($pdb, $values);
            if (!$clause) continue;

            if ($where) $where .= $combine;
            $where .= $clause;
        }

        return $where;
    }
}
