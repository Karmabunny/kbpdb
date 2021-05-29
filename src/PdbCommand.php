<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

/**
 * This holds a query and it's bindings. You can pretty print it.
 *
 * It's probably useless.
 *
 * @package karmabunny\pdb
 */
class PdbCommand
{
    /** @var string */
    public $type;

    /** @var string */
    public $query;

    /** @var array */
    public $values;


    public function __construct(string $query, ...$values)
    {
        $this->type = PdbHelpers::getQueryType($query);
        $this->query = $query;
        $this->values = $values;
    }


    public function __toString()
    {
        $i = 0;
        return preg_replace_callback('/\?/', function() use (&$i) {
            return preg_replace('/\'/', '\\\'', $this->values[$i++]);
        }, $this->query);
    }
}
