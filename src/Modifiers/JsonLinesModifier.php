<?php

namespace karmabunny\pdb\Modifiers;

use JsonException;
use karmabunny\kb\Json;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbDataModifierInterface;

/**
 * Wrap a item with a JSON lines 'concat' modifier.
 *
 * This modifies the update/insert query like {@see ConcatPdbType} but also
 * encodes the value with JSON and prepends a newline character.
 *
 * Note, if the item fails to encode - this will throw a `JsonException`.
 *
 * @package karmabunny\pdb
 */
class JsonLinesModifier implements PdbDataModifierInterface
{

    /** @var mixed */
    public $value;

    /** @var int */
    public $flags;

    /** @var int */
    public $depth;


    /**
     * @param mixed $value
     * @param int $flags
     * @param int $depth
     */
    public function __construct($value, int $flags = 0, int $depth = 512)
    {
        $this->value = $value;
        $this->flags = $flags;
        $this->depth = $depth;
    }


    /** @inheritdoc */
    public function format()
    {
        return Json::encode($this->value, $this->flags, $this->depth);
    }


    /** @inheritdoc */
    public function getBinding(Pdb $pdb, string $column): string
    {
        $column = $pdb->quoteField($column);
        return "{$column} = CONCAT({$column}, ?, '\n')";
    }


    /**
     * Parse a JSON lines string.
     *
     * The result is an iterable of decoded JSON blobs. There's no guarantee
     * that these will be arrays, as is the nature of the PHP json routines.
     *
     * @param string $item
     * @param int $flags
     * @return iterable<mixed>
     * @throws JsonException
     */
    public static function parseJsonLines(string $item, int $flags = 0)
    {
        $tok = strtok($item, "\n");

        while ($tok !== false) {
            yield Json::decode($tok, $flags);
            $tok = strtok("\n");
        }

        // Free up the memory.
        strtok('', '');
    }
}

