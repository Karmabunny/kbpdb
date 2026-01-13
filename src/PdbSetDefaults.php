<?php
namespace karmabunny\pdb;

use karmabunny\interfaces\ArrayableInterface;


/**
 * Represents the plural default values of a SET column
 */
class PdbSetDefaults implements ArrayableInterface
{
    protected string $originalValues;

    /** @var string[] */
    protected array $defaults;

    function __construct(string $default)
    {
        $this->originalValues = $default;
        $this->defaults = explode(',', $default);
    }

    /**
     * @return string[]
     */
    function toArray(): array
    {
        return $this->defaults;
    }

    function __toString(): string
    {
        return $this->originalValues;
    }
}
