<?php
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

    const OPERATORS = [
        'NOT',
        'OR',
        'AND',
        'XOR',
    ];

    const COMPOUNDS = [
        'OR',
        'AND',
        'XOR',
    ];

    /** @var string */
    public $compound;

    /** @var PdbConditionInterface[] */
    public $conditions = [];


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

            $sql .= ' ';
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
