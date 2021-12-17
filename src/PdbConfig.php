<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;
use karmabunny\kb\Arrays;
use karmabunny\kb\Collection;
use karmabunny\kb\Inflector;
use karmabunny\kb\InflectorInterface;
use karmabunny\kb\Reflect;
use karmabunny\pdb\Cache\PdbCache;
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
     * Require an active transaction when calling `commit()`.
     */
    const TX_STRICT_COMMIT = 0x1;

    /**
     * Require an active transaction when calling `rollback()`.
     */
    const TX_STRICT_ROLLBACK = 0x2;

    /**
     * Enable nested transaction (via savepoints).
     *
     * This eliminates recursion exceptions.
     */
    const TX_ENABLE_NESTED = 0x4;

    /**
     * Force the user to provide a transaction key when calling `commit()`.
     *
     * `rollback()` can still be called at any time without a key.
     */
    const TX_FORCE_COMMIT_KEYS = 0x8;

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
     * Note that when you connect to SQL Azure, your username will be `UserName@ServerId`.
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
     * Treat the host as a unix socket for MySQL connections.
     *
     * Note, if this is set `true` (default) then 'localhost' is treated as a socket.
     *
     * Otherwise if a string this is used as the socket path and overrides the `host` setting.
     *
     * @var string|bool
     */
    public $socket = true;

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
     * Secure mode, for Postgres.
     *
     * https://www.postgresql.org/docs/15/libpq-ssl.html#LIBPQ-SSL-SSLMODE-STATEMENTS
     *
     * @var string
     */
    public $sslmode = 'prefer';

    /**
     * Global table prefix.
     *
     * Per-table prefixes can be set in the `table_prefixes` config.
     *
     * @var string
     */
    public $prefix = 'pdb_';

    /**
     * Connection charset, default utf8.
     *
     * @var string
     */
    public $character_set = 'utf8';

    /**
     * Default collation, default utf8.
     *
     * @var string
     */
    public $collation = 'utf8_unicode_ci';

    /**
     * Connection timeout.
     *
     * @var int
     */
    public $timeout = 0;

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
     * If the value is a string or array, this will be configured and asserted
     * as a `PdbDataFormatterInterface` - {@see \karmabunny\kb\Configure}.
     *
     * @var (callable|PdbDataFormatterInterface|string)[] [class => fn]
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
     * @var PdbCache|string|array
     */
    public $cache = PdbStaticCache::class;

    /**
     * An inflector config for pluralisation and such.
     *
     * @var InflectorInterface|string|array
     */
    public $inflector = Inflector::class;

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
     * TX enum
     *
     * Default: strict commit + rollback, enabled nested
     *
     * Ideal: strict commit, force commit keys, enabled nested
     *
     * @var int
     */
    public $transaction_mode = self::TX_STRICT_ROLLBACK | self::TX_STRICT_COMMIT | self::TX_ENABLE_NESTED;


    /** @inheritdoc */
    public function __serialize(): array
    {
        $data = Reflect::getProperties($this);

        unset($data['_pdo']);
        unset($data['formatters']);

        if (is_object($data['cache'])) {
            unset($data['cache']);
        }

        if (is_object($data['reflector'])) {
            unset($data['inflector']);
        }

        return $data;
    }


    /**
     * The 'Data Source Name' used to connect to the database.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function getDsn(): string
    {
        $type = $this->type;

        if ($this->type === 'mssql') {
            $type = 'sqlsrv';
        }

        if ($this->dsn) {
            return $type . ':' . $this->dsn;
        }

        $parts = [];

        if ($this->type === 'mssql') {
            if ($this->host) {
                $server = 'Server=' . $this->host;

                if ($this->port) {
                    $server .= ',' . $this->port;
                }

                $parts[] = $server;
            }

            if ($this->database) {
                $parts[] = 'Database=' . $this->database;
            }
        }
        else if ($this->type === 'mysql') {
            if (is_string($this->socket)) {
                $parts[] = 'unix_socket=' . $this->socket;
            }
            else if ($this->host) {
                // https://www.php.net/manual/en/ref.pdo-mysql.connection.php#refsect1-ref.pdo-mysql.connection-notes
                // php-mysql cheats when given localhost and instead opens
                // a socket. However, IP addresses still work - so if the config
                // specifies 'socket=false' we're going respect that.
                if (
                    $this->socket === false
                    and $this->type === self::TYPE_MYSQL
                    and $this->host === 'localhost'
                ) {
                    $parts[] = 'host=127.0.0.1';
                }
                else {
                    $parts[] = 'host=' . $this->host;
                }
            }

            if ($this->database) {
                $parts[] = 'dbname=' . $this->database;
            }
            if ($this->port) {
                $parts[] = 'port=' . $this->port;
            }
            if ($this->character_set) {
                $parts[] = 'charset=' . $this->character_set;
            }
        }
        else if ($this->type === 'pgsql') {
            if ($this->host) {
                $parts[] = 'host=' . $this->host;
            }
            if ($this->database) {
                $parts[] = 'dbname=' . $this->database;
            }
            if ($this->port) {
                $parts[] = 'port=' . $this->port;
            }
            if ($this->sslmode) {
                $parts[] = 'sslmode=' . $this->sslmode;
            }
        }
        else if ($this->type === 'oracle') {
            $dbname = '';

            if ($this->host) {
                $dbname .= '//' . $this->host;
            }
            if ($this->port) {
                $dbname .= ':' . $this->port;
            }

            if ($dbname) {
                $dbname .= '/';
            }

            $dbname .= $this->database;

            $parts[] = 'dbname=' . $dbname;

            if ($this->character_set) {
                $parts[] = 'charset=' . $this->character_set;
            }
        }
        else if ($this->type === 'sqlite') {
            $parts[] = $this->database;
        }
        else {
            throw new InvalidArgumentException('Unknown db type: ' . $this->type);
        }

        return $type . ':' . implode(';', $parts);
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
