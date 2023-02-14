<?php

namespace karmabunny\pdb\DataBinders;

use JsonException;
use karmabunny\kb\Json;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbDataBinderInterface;

/**
 * Wrap a item with a JSON lines 'concat' binding.
 *
 * This modifies the update/insert query like {@see ConcatBinder} but also
 * encodes the value with JSON and prepends a newline character.
 *
 * Note, if the item fails to encode - this will throw a `JsonException`.
 *
 * @package karmabunny\pdb
 */
class JsonLinesBinder implements PdbDataBinderInterface
{

    /** @var mixed */
    public $value;

    /** @var int */
    public $flags;


    /**
     * @param mixed $value
     * @param int $flags
     */
    public function __construct($value, int $flags = 0)
    {
        $this->value = $value;
        $this->flags = $flags;
    }


    /** @inheritdoc */
    public function getBindingValue()
    {
        return Json::encode($this->value, $this->flags) . "\n";
    }


    /** @inheritdoc */
    public function getBindingQuery(Pdb $pdb, string $column): string
    {
        $column = $pdb->quoteField($column);
        return "{$column} = CONCAT({$column}, ?)";
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

