<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use karmabunny\pdb\Pdb;

/**
 *
 * @package karmabunny\pdb
 */
class PdbRawCondition implements PdbConditionInterface
{

    /** @var string */
    public $sql;

    /** @var array */
    public $params = [];


    /**
     * Create a condition.
     *
     * @param string $sql
     * @param array $params
     */
    public function __construct(string $sql, array $params = [])
    {
        $this->sql = $sql;
        $this->params = $params;
    }


    /** @inheritdoc */
    public function build(Pdb $pdb, array &$values): string
    {
        $values = array_merge($values, $this->params);
        return $this->sql;
    }


    /** @inheritdoc */
    public function validate()
    {
    }


    /** @inheritdoc */
    public function getPreviewSql(): string
    {
        return $this->sql;
    }


    /** @inheritdoc */
    public function __toString()
    {
        return $this->sql;
    }
}
