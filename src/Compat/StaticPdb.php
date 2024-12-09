<?php

namespace karmabunny\pdb\Compat;

use BadMethodCallException;
use InvalidArgumentException;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Models\PdbReturn;
use karmabunny\pdb\Models\PdbTransaction;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
use karmabunny\pdb\PdbHelpers;
use karmabunny\pdb\PdbQuery;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class for doing database queries via PDO (PDO Database => Pdb).
 *
 * This a compatibility class, all methods are static variants.
 *
 * Extend this class and implement the `getConfig()` method.
 *
 * @method static string getServerVersion()
 * @method static void setFormatter(string $class_name, callable $func)
 * @method static void removeFormatter(string $class_name)
 * @method static void setTablePrefixOverride(string $table, string $prefix)
 * @method static string getPrefix(string $table = '*')
 * @method static PDOStatement prepare(string $query)
 * @method static array|string|int|null|PDOStatement execute(PDOStatement $st, array $params, string|array|PdbReturn $config)
 * @method static array|string|int|null formatRs(PDOStatement $rs, string $type)
 * @method static void validateIdentifier(string $name, bool $loose = false)
 * @method static void validateIdentifierExtended(string $name, bool $loose = false)
 * @method static int insert(string $table, array $data)
 * @method static int getLastInsertId()
 * @method static string buildClause(array $conditions, array &$values, string $combine = 'AND')
 * @method static int update(string $table, array $data, array $conditions)
 * @method static int delete(string $table, array $conditions)
 * @method static int upsert(string $table, array $data, array $conditions)
 * @method static PdbQuery find(string $table, array $conditions = [])
 * @method static bool inTransaction()
 * @method static PdbTransaction transact()
 * @method static mixed withTransaction(callable $callback)
 * @method static string savepoint(string $name = null)
 * @method static void commit(string $name = null)
 * @method static void rollback(string $name = null)
 * @method static string now()
 * @method static array lookup(string $table, array $conditions = [], array $order = ['name'], string $name = 'name')
 * @method static array get(string $table, int $id)
 * @method static bool recordExists(string $table, array $conditions)
 * @method static array extractEnumArr(string $table, string $column)
 * @method static bool validateEnum(string $table, array $field)
 * @method static PdbForeignKey[] getDependentKeys(string $table)
 * @method static PdbForeignKey[] getForeignKeys(string $table)
 *
 * @method static array getBindSubset(string $q, array $binds)
 * @method static string prettyQueryIndentation(string $query)
 * @method static string prettyQuery(string $query, array $values)
 * @method static string stripStrings(string $q)
 * @method static string likeEscape(string $str)
 * @method static array convertEnumArr(string $enum_defn)
 *
 * @method static string quote(string $field, string $type)
 * @method static string[] quoteAll(string $field, string $type)
 * @method static string quoteValue(string $value)
 * @method static string quoteField(string $field, bool $prefixes = false)
 * @method static string quoteIdentifiers(string $query, array|null $tables = null, bool $prefixes = false)
 * @method static PdbIndex[] indexList(string $table)
 * @method static PdbColumn[] fieldList(string $table)
 * @method static string generateUid(string $table, int $id)
 * @method static void validateReturnType(string $name)
 * @method static void validateDirection(string $name)
 * @method static void validateAlias(string $name, bool $loose = false)
 *
 * @package karmabunny\pdb\Compat
 */
abstract class StaticPdb
{

    /** @var Pdb[] */
    protected static $connections = [];


    /**
     * Create a PDB configuration.
     *
     * @param string|null $name
     * @return PdbConfig
     */
    public static abstract function getConfig(string $name = null): PdbConfig;


    /**
     * Get the pdb instance.
     *
     * Override this if you wish to store the instance elsewhere.
     *
     * Note however this contains logic for the read/write/only/override connections.
     *
     * @param string $type
     * @return Pdb
     */
    public static function getInstance(string $type = 'RW'): Pdb
    {
        if (isset(static::$connections['override'])) {
            $name = 'override';
        }
        else if ($type == 'RO' or $type == 'read_only') {
            $name = 'read_only';
        }
        else {
            $name = 'default';
        }

        // A cached version.
        if ($pdb = static::$connections[$name] ?? null) {
            return $pdb;
        }

        // Start fresh.
        $config = static::getConfig($name);
        $pdb = Pdb::create($config);

        static::$connections[$name] = $pdb;
        return $pdb;
    }


    /**
     * Loads a config and creates a new PDO connection with it.
     *
     * You probably want getConnection() or getInstance().
     *
     * @param string|PdbConfig|array $name
     * @return PDO
     * @throws PDOException If connection fails
     */
    public static function connect($name): PDO
    {
        if (is_string($name)) {
            $config = static::getConfig($name);
        }
        else if (is_array($name)) {
            $config = new PdbConfig($name);
        }
        else if ($name instanceof PdbConfig) {
            $config = $name;
        }
        // @phpstan-ignore-next-line
        else {
            throw new InvalidArgumentException("Invalid config name");
        }

        return Pdb::connect($config);
    }


    /**
     * Wrap everything.
     *
     * @param mixed $name
     * @param mixed $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $pdb = static::getInstance();

        try {
            if (method_exists($pdb, $name)) {
                return $pdb->$name(...$arguments);
            }
        }
        catch (ConnectionException $exception) {
            if ($exception->getPrevious() instanceof PDOException) {
                throw $exception->getPrevious();
            }
            throw new PDOException($exception->getMessage(), $exception->getCode());
        }

        if (method_exists(PdbHelpers::class, $name)) {
            return call_user_func([PdbHelpers::class, $name], ...$arguments);
        }

        throw new BadMethodCallException("Method {$name} does not exist");
    }


    /**
     * Builds a clause string by combining conditions, e.g. for a WHERE or ON clause.
     * The resultant clause will contain ? markers for safe use in a prepared SQL statement.
     * The statement and the generated $values can then be run via {@see Pdb::query}.
     *
     * Each condition (see $conditions) is one of:
     *   - The scalar value 1 or 0 (to match either all or no records)
     *   - A column => value pair
     *   - An array with three elements: [column, operator, value(s)].
     *   - An array like: [operator, column => value].
     *
     * Conditions are usually combined using AND, but can also be OR or XOR.
     *
     * @param array $conditions
     * @param array $values
     * @param string $combine
     * @return string
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public static function buildClause(array $conditions, array &$values, $combine = 'AND')
    {
        // Because references can't be passed through variadic args.
        $pdb = static::getInstance();
        return $pdb->buildClause($conditions, $values, $combine);
    }


    /**
     * Executes a PDO query
     *
     * For the return type 'pdo', a PDOStatement is returned. You need to close it using $res->closeCursor()
     *     once you're finished.
     * For the return type 'null', nothing is returned.
     * For the return type 'count', a count of rows is returned.
     * Additional return types are available; {@see Pdb::formatRs} for a full list.
     *
     * When working with datasets larger than about 50 rows, you may run out of ram when using
     * return types other than 'pdo', 'null', 'count' or 'val' because the other types all return the values as arrays
     *
     * Return types:
     * - PDOStatement For type 'pdo'
     * - int For type 'count'
     * - null For type 'null'
     * - mixed For all other types; see {@see Pdb::formatRs}
     *
     * @param string $query The query to execute. Prefix a table name with a tilde (~) to automatically include the
     *        table prefix, e.g. ~pages will be converted to fwc_pages
     * @param array $params Parameters to bind to the query
     * @param string|array|PdbReturn $config a return type or config {@see PdbReturn}
     * @return array|string|int|null|PDOStatement
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     * @throws ConnectionException If the connection fails
     */
    public static function query(string $query, array $params, $config)
    {
        $pdb = static::getInstance();
        $st = $pdb->prepare($query);

        // This doesn't exist the new Pdb, instead use prepare().
        // This exists only for backward compat.
        if ($config === 'prep') {
            if (count($params) !== 0) {
                $err = 'You cannot provide parameters when preparing statements';
                throw new InvalidArgumentException($err);
            }

            return $st;
        }

        return $pdb->execute($st, $params, $config);
    }


    /**
     * Alias for {@see Pdb::query}
     *
     * @param string $query The query to execute. Prefix a table name with a tilde (~) to automatically include the
     *        table prefix, e.g. ~pages will be converted to fwc_pages
     * @param array $params Parameters to bind to the query
     * @param string|array|PdbReturn $config a return type or config {@see PdbReturn}
     * @return array|string|int|null|PDOStatement
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     * @throws ConnectionException If the connection fails
     */
    public static function q($query, array $params, $config)
    {
        return static::query($query, $params, $config);
    }


    /**
     * Gets a PDO connection, creating a new one if necessary
     *
     * @param string $type 'RW': read-write, or 'RO': read-only. If replication
     *        isn't being used, then only 'RW' is ever necessary.
     * @return PDO
     * @throws PDOException If connection fails
     */
    public static function getConnection($type = 'RW')
    {
        try {
            $pdb = static::getInstance($type);
            return $pdb->getConnection();
        }
        catch (ConnectionException $exception) {
            if ($exception->getPrevious() instanceof PDOException) {
                throw $exception->getPrevious();
            }
            throw new PDOException($exception->getMessage(), $exception->getCode());
        }
    }


    /**
     * Gets the prefix to prepend to table names.
     *
     * @return string
     */
    public static function prefix()
    {
        $pdb = static::getInstance();
        return $pdb->config->prefix;
    }


    /**
     * Sets the prefix to prepend to table names.
     * Only use this to override the existing prefix; e.g. in the Preview helper
     *
     * @param string $prefix The overriding prefix
     */
    public static function setPrefix(string $prefix)
    {
        $pdb = static::getInstance();
        $pdb->config->prefix = $prefix;
    }


    /**
     * Set a logging handler function
     *
     * This function will be called after each query
     * to allow calling code to do extra debugging or logging
     *
     * The function signature should be:
     *    function(string $query, array $params, PDOStatement|QueryException $result)
     *
     * @param callable $func The logging function to use
     */
    public static function setLogHandler(callable $func)
    {
        $pdb = static::getInstance();
        $pdb->setDebugger($func);
    }


    /**
     * Clear the log handler
     */
    public static function clearLogHandler()
    {
        $pdb = static::getInstance();
        $pdb->setDebugger(null);
    }


    /**
     * Set a PDO connection to use instead of the internal connection logic
     * The specified connection may even be for a different database
     *
     * NOTE: The flag indicating if the driver is currently in a transaction
     * ({@see inTransaction}) does not get changed by calls to this function,
     * so it may not produce correct results in that case.
     *
     * @example
     * $mssql = new PDO('obdc:ms-sql-server');
     * Pdb::setOverrideConnection($mssql);
     * $res = Pdb::query('SELECT TOP 3 FROM [my table]', [], 'row');
     *
     * @param PDO $connection Any PDO connection.
     * @param PdbConfig|array $config
     */
    public static function setOverrideConnection(PDO $connection, $config = [])
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        $config->_pdo = $connection;
        $config->type = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        static::$connections['override'] = Pdb::create($config);
    }


    /**
     * Clear any overridden connection, reverting behaviour back to
     * the default connection logic.
     */
    public static function clearOverrideConnection()
    {
        unset(static::$connections['override']);
    }


    /**
     * Replaces tilde placeholders with table prefixes, and quotes tables
     * according to the rules of the underlying DBMS.
     *
     * COMPATIBILITY NOTE: The connection is _not used_ to determine the escape
     * characters. It will use the existing database connection/config.`
     *
     * @param PDO $pdo The database connection, for determining table quoting rules
     * @param string $query Query which contains tilde placeholders, e.g. 'SELECT * FROM ~pages WHERE id = 1'
     * @return string Query with tildes replaced by prefixes, e.g. 'SELECT * FROM `sprout_pages` WHERE id = 1'
     */
    public static function insertPrefixes(PDO $pdo, string $query): string
    {
        $pdb = static::getInstance();
        return $pdb->insertPrefixes($query);
    }
}
