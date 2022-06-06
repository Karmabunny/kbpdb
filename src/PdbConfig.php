<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use karmabunny\kb\Arrays;
use karmabunny\kb\Collection;
use karmabunny\pdb\Cache\PdbStaticCache;
use PDO;

/**
 * The configuration for a Pdb object.
 *
 * This provides per-connection settings.
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

    /**
     * One of the TYPE enum.
     *
     * - mysql (full support)
     * - sqlite (partial)
     * - pgsql (partial)
     * - mssql (untested)
     * - oracle (untested)
     *
     * @var string
     */
    public $type;

    /**
     * Connection hostname or IP address.
     *
     * @var string
     */
    public $host;

    /**
     * Connection username.
     *
     * @var string
     */
    public $user;

    /**
     * Connection password.
     *
     * @var string
     */
    public $pass;

    /**
     * Database name.
     *
     * @var string
     */
    public $database;

    /**
     * Port number, or null for default.
     *
     * @var int|null
     */
    public $port = null;

    /**
     * Database schema, for Postgres.
     *
     * @var string
     */
    public $schema = 'public';

    /**
     * Global table prefix.
     *
     * Per-table prefixes can be set in the `table_prefixes` config.
     *
     * @var string
     */
    public $prefix = 'bloom_';

    /**
     * Connection charset, default utf8.
     *
     * @var string
     */
    public $character_set = 'utf8';

    /**
     * Namespace for UUIDv5 generation.
     *
     * @var string
     */
    public $namespace = Pdb::UUID_NAMESPACE;

    /**
     * Per-table prefixes.
     *
     * These override the global `prefix` config.
     *
     * @var string[] [table => prefix]
     */
    public $table_prefixes = [];

    /**
     * String formatters for class parameters.
     *
     * The result must return a string, for example:
     *
     * ```
     * $formatter = function($date) {
     *     return date('Y-m-d H:i:s', $date);
     * }
     * $config->formatters[DateTimeInterface::class] = $formatter;
     * ```
     *
     * @var callable[] [class => fn]
     */
    public $formatters = [];

    /**
     * Connection string override.
     *
     * This is required for SQLite.
     *
     * E.g. `/path/to/db.sqlite`
     *
     * @var string|null
     */
    public $dsn;

    /**
     * Session variables per call to `connect()`.
     *
     * These are driver specific.
     *
     * Currently supported:
     * - mysql
     * - pgsql
     *
     * @var array [ varname => value ]
     */
    public $session = [];

    /**
     * Driver specific hacks.
     *
     * See documentation per driver for details.
     *
     * @var string[]
     */
    public $hacks = [];

    /**
     * A caching class extending PdbCache.
     *
     * By default this is a static cache and data will only live for the
     * duration of the request.
     *
     * This can be a class string, array config, or object instance.
     *
     * Example:
     * ```
     * $config->cache = [
     *    PdbRedisCache::class => [
     *       'host' => 'localhost',
     *       'prefix' => 'pdb:',
     *    ],
     * ];
     * ```
     *
     * @var string|array|object
     */
    public $cache = PdbStaticCache::class;

    /**
     * An identity key for this connection.
     *
     * Used for distinguishing between connections within a cache.
     *
     * By default this is is a shasum of a the DSN string.
     *
     * @var string|null
     */
    public $identity;

    /**
     * Default caching TTL, in seconds.
     *
     * Each call to `query()` is able to override this. Or via the `cache()`
     * modifier in a PdbQuery (recommended).
     *
     * {@see PdbReturn}
     *
     * @var int seconds
     */
    public $ttl = 10;

    /**
     * This is used in the Sprout compatibility layer.
     *
     * Don't use this.
     *
     * Unless maybe you're REALLY sure about it.
     *
     * @var PDO|null
     */
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
     * The identity key for this config.
     *
     * By default this is a shasum of the DSN string. Or customize it with the
     * 'identity' property.
     *
     * This is used to provide cache keys.
     *
     * @return string
     */
    public function getIdentity(): string
    {
        if (!empty($this->identity)) {
            return $this->identity;
        }

        // TODO would a random string be better?
        // Then we get per-instance separation of cache keys.

        return sha1($this->getDsn());
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
