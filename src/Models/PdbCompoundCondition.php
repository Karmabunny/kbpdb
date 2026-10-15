<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use karmabunny\pdb\Exceptions\InvalidConditionException;
use karmabunny\pdb\Pdb;

/**
 *
 * @package karmabunny\pdb
 */
class PdbCompoundCondition implements PdbConditionInterface
{

    const NOT = 'NOT';
    const NOT_OR = 'NOT OR';
    const OR = 'OR';
    const AND = 'AND';
    const XOR = 'XOR';

    const OPERATORS = [
        self::NOT,
        self::NOT_OR,
        self::OR,
        self::AND,
        self::XOR,
    ];

    const COMPOUNDS = [
        self::OR,
        self::AND,
        self::XOR,
    ];

    /** @var string */
    public string $compound;

    /** @var PdbConditionInterface[] */
    public array $conditions = [];


    /**
     * Create a condition.
     *
     * @param string $compound
     * @param PdbConditionInterface[] $conditions
     */
    public function __construct(string $compound, array $conditions)
    {
        $this->compound = trim(strtoupper($compound));
        $this->compound = $compound;
        $this->conditions = $conditions;
    }


    /** @inheritdoc */
    public function validate()
    {
        if (!in_array($this->compound, self::OPERATORS)) {
            $message = "Unknown compound operator: '{$this->compound}'";
            throw (new InvalidConditionException($message))
                ->withCondition($this);
        }

        foreach ($this->conditions as $condition) {
            // @phpstan-ignore-next-line: assert doc types.
            if (!$condition instanceof PdbConditionInterface) {
                throw (new InvalidConditionException('Invalid condition'))
                    ->withCondition($this)
                    ->withActual($condition);
            }

            $condition->validate();
        }
    }


    /** @inheritdoc */
    public function build(Pdb $pdb, array &$values): string
    {
        // Special 'not' operator will perform a nested 'and'.
        if ($this->compound === 'NOT') {
            $compound = 'AND';
            $sql = 'NOT ';
        }
        else if ($this->compound === 'NOT OR') {
            $compound = 'OR';
            $sql = 'NOT ';
        }
        else {
            $compound = $this->compound;
            $sql = '';
        }

        $sql .= '(';
        $first = true;

        foreach ($this->conditions as $condition) {
            if (!$first) {
                $sql .= " {$compound} ";
            }

            $sql .= $condition->build($pdb, $values);
            $first = false;
        }

        $sql .= ')';
        return $sql;
    }


    /** @inheritdoc */
    public function getPreviewSql(): string
    {
        if ($this->compound === 'NOT') {
            $compound = 'AND';
            $sql = 'NOT ';
        }
        else if ($this->compound === 'NOT OR') {
            $compound = 'OR';
            $sql = 'NOT ';
        }
        else {
            $compound = $this->compound;
            $sql = '';
        }


        $sql .= '(';
        $first = true;

        foreach ($this->conditions as $condition) {
            if (!$first) {
                $sql .= " {$compound} ";
            }

            $sql .= $condition->getPreviewSql() ?: '(!!)';
            $first = false;
        }

        $sql .= ')';
        return $sql;
    }


    /** @inheritdoc */
    public function __toString()
    {
        $preview = $this->getPreviewSql();
        return $preview;
    }
}
