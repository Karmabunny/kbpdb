<?php

namespace karmabunny\pdb\Compat;

use InvalidArgumentException;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbHelpers;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class for doing database queries via PDO (PDO Database => Pdb).
 *
 * This a compatibility class, all methods are static variants.
 *
 * Extend this class and implement the `getInstance()` method.
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
abstract class StaticPdb
{

    /**
     * Get the pdb instance.
     *
     * @param string $config
     * @return Pdb
     */
    public static abstract function getInstance(string $config = 'RW'): Pdb;


    /**
     * Loads a config and creates a new PDO connection with it.
     *
     * You probably want getConnection() or getInstance().
     *
     * @param string $config
     * @return PDO
     * @throws PDOException If connection fails
     */
    public static abstract function connect(string $config): PDO;


    /**
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

        return null;
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
     * Gets a PDO connection, creating a new one if necessary
     *
     * @param string $type 'RW': read-write, or 'RO': read-only. If replication
     *        isn't being used, then only 'RW' is ever necessary.
     * @return PDO
     * @throws PDOException If connection fails
     */
    public static function getConnection($type = 'RW')
    {
        // TODO finish this properly.

        try {
            $pdb = static::getInstance();
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
        return $pdb->getPrefix();
    }


    /**
     * Sets the prefix to prepend to table names.
     * Only use this to override the existing prefix; e.g. in the Preview helper
     * @param string $prefix The overriding prefix
     */
    public static function setPrefix(string $prefix)
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
