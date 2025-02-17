<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use InvalidArgumentException;
use karmabunny\pdb\Models\PdbConditionInterface;

/**
 *
 */
class InvalidConditionException extends InvalidArgumentException
{
    /** @var PdbConditionInterface|null */
    public $condition = null;

    /** @var string|null */
    public $actual = null;


    /**
     *
     * @param PdbConditionInterface $condition
     * @return static
     */
    public function withCondition(PdbConditionInterface $condition)
    {
        $this->condition = $condition;

        if ($preview = $this->condition->getPreviewSql()) {
            $this->message .= " \"{$preview}\"";
        }

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
