<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use InvalidArgumentException;
use karmabunny\pdb\Models\PdbCondition;

/**
 *
 */
class InvalidConditionException extends InvalidArgumentException
{
    /** @var PdbCondition|null */
    public $condition = null;

    /** @var string|null */
    public $actual = null;


    /**
     *
     * @param PdbCondition $condition
     * @return static
     */
    public function withCondition(PdbCondition $condition)
    {
        $this->condition = $condition;

        $preview = $this->condition->getPreviewSql() ?: '???';
        $this->message .= " \"{$preview}\"";

        return $this;
    }


    /**
     *
     * @param mixed $actual
     * @return static
     */
    public function withActual($actual)
    {
        if (!is_scalar($actual)) {
            $actual = gettype($actual);
        }

        $this->message .= ', got: ' . $actual;
        $this->actual = $actual;
        return $this;
    }
}
