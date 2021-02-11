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

    public string $type;

    public string $host;

    public string $user;

    public string $pass;

    public string $database;

    public string $port = null;

    public string $prefix = 'bloom_';

    public string $character_set = 'utf8';


    public function getDsn(): string
    {
        $dsn = "{$this->type}:host={$this->host}";
        $dsn .= ";dbname={$this->db}";
        $dsn .= ";charset={$this->character_set}";

        if ($this->port) {
            $dsn .= ";port={$this->port}";
        }

        return $dsn;
    }
}
