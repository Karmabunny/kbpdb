<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbConfig extends Collection
{

    /** @var string */
    public $type;

    /** @var string */
    public $host;

    /** @var string */
    public $user;

    /** @var string */
    public $pass;

    /** @var string */
    public $database;

    /** @var string */
    public $port = null;

    /** @var string */
    public $prefix = 'bloom_';

    /** @var string */
    public $character_set = 'utf8';

    /** @var string[] [table => prefix] */
    public $table_prefixes = [];

    /** @var callable[] [class => fn] */
    public $formatters = [];


    public function getDsn(): string
    {
        $dsn = "{$this->type}:host={$this->host}";
        $dsn .= ";dbname={$this->database}";
        $dsn .= ";charset={$this->character_set}";

        if ($this->port) {
            $dsn .= ";port={$this->port}";
        }

        return $dsn;
    }
}
