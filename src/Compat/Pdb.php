<?php

namespace karmabunny\pdb\Compat;

use InvalidArgumentException;
use karmabunny\pdb\PdbConfig;
use karmabunny\pdb\PdbHelpers;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class for doing database queries via PDO (PDO Database => Pdb).
 *
 * This a compatibility class, all methods are static variants.
 *
 * To patch this in, start by including `Pdb::config()` somewhere in your
 * bootstrap code to configure things.
 *
 * TODOs:
 * - connect(string) compat
 * - RW/RO/override connections
 * - log handler
 * - prefix swapping
 * - compat insertPrefixes()
 * - setPrefix()
 * - rework PdbForeignKey/PdbColumn methods
 *
 * @method static PDO connect(array|PdbConfig $config)
 * @method static PDO getConnection()
 * @method static void setFormatter(string $class_name, callable $func)
 * @method static void removeFormatter(string $class_name)
 * @method static void setTablePrefixOverride(string $table, string $prefix)
 * @method static PDO insertPrefixes(string $query)
 * @method static string getPrefix()
 * @method static array|string|int|null|PDOStatement q(string $query, array $params, string $return_type)
 * @method static array|string|int|null|PDOStatement query(string $query, array $params, string $return_type)
 * @method static PDOStatement prepare(string $query)
 * @method static array|string|int|null|PDOStatement execute(PDOStatement $st, array $params, string $return_type)
 * @method static array|string|int|null formatRs(PDOStatement $rs, string $type)
 * @method static void validateIdentifier(string $name)
 * @method static void validateIdentifierExtended(string $name)
 * @method static int insert(string $table, array $data)
 * @method static int getLastInsertId()
 * @method static string buildClause(array $conditions, array &$values, string $combine = 'AND')
 * @method static int update(string $table, array $data, array $conditions)
 * @method static int delete(string $table, array $conditions)
 * @method static bool inTransaction()
 * @method static void transact()
 * @method static void commit()
 * @method static void rollback()
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
 * @method static string likeEscape(string $str)
 * @method static array convertEnumArr(string $enum_defn)
 *
 * @method static bool upsert(string $table, array $data, array $conditions)
 * @method static string quote(string $field, string $type)
 * @method static string[] quoteAll(string $field, string $type)
 * @method static string quoteValue(string $value)
 * @method static string quoteField(string $field)
 * @method static PdbIndex[] indexList(string $table)
 * @method static PdbColumn[] fieldList(string $table)
 * @method static string generateUid(string $table, int $id)
 * @method static void validateReturnType(string $name)
 * @method static void validateDirection(string $name)
 * @method static void validateAlias(string $name)
 *
 * @package karmabunny\pdb\Compat
 */
class Pdb
{
    /** @var \karmabunny\pdb\Pdb */
    public static $pdb;


    /**
     *
     * @param PdbConfig|array $config
     * @return void
     */
    public static function config($config)
    {
        if (isset(self::$pdb)) return;
        self::$pdb = \karmabunny\pdb\Pdb::create($config);
    }


    /**
     *
     * @param mixed $name
     * @param mixed $arguments
     * @return void
     */
    public static function __callStatic($name, $arguments)
    {
        if (method_exists(self::$pdb, $name)) {
            return self::$pdb->$name(...$arguments);
        }

        if (method_exists(PdbHelpers::class, $name)) {
            return call_user_func([PdbHelpers::class, $name], ...$arguments);
        }

        return null;
    }


    /**
     *
     * @param mixed $name
     * @param mixed $arguments
     * @return void
     */
    public function __call($name, $arguments)
    {
        return self::__callStatic($name, $arguments);
    }


    /**
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
        return self::$pdb->buildClause($conditions, $values, $combine);
    }


    /**
     * Gets the prefix to prepend to table names.
     *
     * @return string
     */
    public static function prefix()
    {
        return self::$pdb->getPrefix();
    }


    /**
     * Sets the prefix to prepend to table names.
     * Only use this to override the existing prefix; e.g. in the Preview helper
     * @param string $prefix The overriding prefix
     */
    public static function setPrefix()
    {
        // TODO
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
        // TODO
    }


    /**
     * Clear the log handler
     */
    public static function clearLogHandler()
    {
        // TODO
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
     */
    public static function setOverrideConnection(PDO $connection)
    {
        // TODO
    }


    /**
     * Clear any overridden connection, reverting behaviour back to
     * the default connection logic.
     */
    public static function clearOverrideConnection()
    {
        // TODO
    }
}
