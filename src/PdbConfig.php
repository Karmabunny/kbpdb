<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use karmabunny\kb\Arrays;
use karmabunny\kb\Collection;
use PDO;

/**
 *
 * @package karmabunny\pdb
 */
class PdbConfig extends Collection
{

    const TYPE_MYSQL  = 'mysql';
    const TYPE_SQLITE = 'sqlite';
    const TYPE_PGSQL  = 'pgsql';
    const TYPE_MSSQL  = 'mssql';
    const TYPE_ORACLE = 'oracle';

    /** @deprecated use session: `['sql_mode' => 'NO_ENGINE_SUBSTITUTION']` */
    const HACK_NO_ENGINE_SUBSTITUTION = 'no_engine_substitution';

    /** @deprecated use session: `['time_zone' => 'Country/Region']` */
    const HACK_TIME_ZONE = 'time_zone';

    /** Insert some custom functions. */
    const HACK_SQLITE_FUNCTIONS = 'sqlite_functions';

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
    public $schema = 'public';

    /** @var string */
    public $prefix = 'bloom_';

    /** @var string */
    public $character_set = 'utf8';

    /** @var string */
    public $namespace = Pdb::UUID_NAMESPACE;

    /** @var string[] [table => prefix] */
    public $table_prefixes = [];

    /** @var callable[] [class => fn] */
    public $formatters = [];

    /** @var string|null */
    public $dsn;

    /** @var array [ varname => value ] */
    public $session = [];

    /** @var string[] */
    public $hacks = [];

    /** @var PDO|null */
    public $_pdo;


    /**
     * The 'Data Source Name' used to connect to the database.
     *
     * @return string
     */
    public function getDsn(): string
    {
        if ($this->dsn) {
            return $this->type . ':' . $this->dsn;
        }
        else {
            $parts = [];

            if ($this->host) {
                $parts[] = 'host=' . $this->host;
            }
            if ($this->database) {
                $parts[] = 'dbname=' . $this->database;
            }
            if ($this->character_set) {
                $parts[] = 'charset=' . $this->character_set;
            }
            if ($this->port) {
                $parts[] = 'port=' . $this->port;
            }

            return $this->type . ':' . implode(';', $parts);
        }
    }


    /**
     * The hacks applied to this configuration.
     *
     * @return array [option => value]
     */
    public function getHacks(): array
    {
        return Arrays::normalizeOptions($this->hacks, true);
    }


    /**
     * Get parameter quotes as appropriate for the underlying DBMS.
     *
     * For things like fields, tables, etc.
     *
     * @return string[] [left, right]
     */
    public function getFieldQuotes()
    {
        switch ($this->type) {
            case PdbConfig::TYPE_MYSQL:
                $lquote = $rquote = '`';
                break;

            case PdbConfig::TYPE_MSSQL:
                $lquote = '[';
                $rquote = ']';
                break;

            case PdbConfig::TYPE_SQLITE:
            case PdbConfig::TYPE_PGSQL:
            case PdbConfig::TYPE_ORACLE:
            default:
                $lquote = $rquote = '"';
        }

        return [$lquote, $rquote];
    }


    /**
     * Get prefixing rules.
     *
     * The wildcard '*' is used to match all tables.
     *
     * If this array is empty, no prefixing is active.
     *
     * Keys are un-prefixed, i.e. `['users' => 'sprout_']`
     *
     * @return string[] [ table => prefix ]
     */
    public function getPrefixes(): array
    {
        $prefixes = [];

        if ($this->prefix) {
            $prefixes['*'] = $this->prefix;
        }

        return array_merge($prefixes, $this->table_prefixes);
    }

}
