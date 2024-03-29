<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb\DataBinders;

use InvalidArgumentException;
use karmabunny\pdb\PdbDataFormatterInterface;
use karmabunny\pdb\PdbDataFormatterTrait;

/**
 * Wrap a callable into a formatter.
 *
 * @package karmabunny\pdb
 */
class CallableFormatter implements PdbDataFormatterInterface
{
    use PdbDataFormatterTrait;


    /** @var callable */
    public $fn;


    /**
     * @param callable $fn
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }


    /** @inheritdoc */
    public function format($value): string
    {
        $value = ($this->fn)($value);

        if (is_bool($value)) {
            $value = (int) $value;
        }

        if (!is_scalar($value)) {
            $class_name = get_class($value);
            throw new InvalidArgumentException("Formatter for type '{$class_name}' must return a string or int");
        }

        return (string) $value;
    }
}
