<?php
namespace karmabunny\pdb;

use karmabunny\interfaces\ArrayableInterface;
use karmabunny\kb\Json;

/**
 * Represents a possible JSON value for an array property
 *
 * N.B. MySQL allows creation of columns with a JSON type, but they end up as LONGTEXT columns
 * with JSON validation constraints
 * This should also support (PostGreSQL) JSON and JSONB columns
 */
class PdbJsonDefault implements ArrayableInterface
{
    protected string $default;

    function __construct(string $default)
    {
        $this->default = $default;
    }

    /**
     * @return array<mixed, mixed>
     */
    function toArray(): array
    {
        return Json::decode($this->default);
    }

    function __toString(): string
    {
        return $this->default;
    }
}
