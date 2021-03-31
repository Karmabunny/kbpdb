<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;
use karmabunny\kb\Log;
use karmabunny\kb\Loggable;
use karmabunny\kb\LoggerTrait;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Exceptions\RowMissingException;
use karmabunny\pdb\Exceptions\TransactionRecursionException;
use karmabunny\pdb\Drivers\PdbMysql;
use karmabunny\pdb\Drivers\PdbNoDriver;
use karmabunny\pdb\Drivers\PdbSqlite;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use PDO;
use PDOException;
use PDOStatement;


/**
 * Class for doing database queries via PDO (PDO Database => Pdb)
 *
 * @package karmabunny\pdb
 */
abstract class Pdb implements Loggable
{
    use LoggerTrait;

    const UUID_NAMESPACE = '3afdd7bd-b030-4c46-a3b6-f4d600670865';

    const QUOTE_VALUE = 'value';

    const QUOTE_FIELD = 'field';


    const RETURN_PDO = 'pdo';

    const RETURN_NULL = 'null';

    const RETURN_COUNT = 'count';

    const RETURN_ARR = 'arr';

    const RETURN_ARR_NUM = 'arr-num';

    const RETURN_ROW = 'row';

    const RETURN_ROW_NUM = 'row-num';

    const RETURN_MAP = 'map';

    const RETURN_MAP_ARR = 'map-arr';

    const RETURN_VAL = 'val';

    const RETURN_COL = 'col';

    const RETURN_TYPES = [
        self::RETURN_PDO,
        self::RETURN_NULL,
        self::RETURN_COUNT,
        self::RETURN_ARR,
        self::RETURN_ARR_NUM,
        self::RETURN_ROW,
        self::RETURN_ROW_NUM,
        self::RETURN_MAP,
        self::RETURN_MAP_ARR,
        self::RETURN_VAL,
        self::RETURN_COL,
    ];


    /** @var PdbConfig */
    public $config;

    /** @var PDO|null */
    protected $connection;

    /** @var PDO|null */
    protected $override_connection = null;

    /** @var bool */
    protected $in_transaction = false;

    /** @var int|null */
    protected $last_insert_id = null;


    /**
     *
     * @param PdbConfig|array $config
     */
    public function __construct($config)
    {
        if (!($config instanceof PdbConfig)) {
            $this->config = new PdbConfig($config);
        }
        else {
            $this->config = clone $config;
        }
    }


    /**
     *
     * @param PdConfig|array $config
     * @return PdbExtended
     */
    public static function create($config)
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        switch ($config->type) {
            case 'mysql':
                return new PdbMysql($config);

            case 'sqlite':
                return new PdbSqlite($config);

            default:
                return new PdbNoDriver($config);
        }
    }


    /**
     * Loads db config and creates a new PDO connection with it.
     *
     * @param PdbConfig|array $config
     * @return PDO
     */
    public static function connect($config)
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        $pdo = new PDO($config->getDsn(), $config->user, $config->pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }


    // ===========================================================
    //     Settings and stuff
    // ===========================================================


    /**
     * Set the formatter function for a given class.
     *
     * Formatter functions are called when an object is passed to Pdb
     * as a bind parameter or in other ways.
     *
     * The function signature should be:
     *    function(mixed $val) : string
     *
     * @param string $class_name The class to attach the formatter to
     * @param callable $func The formatter function to use
     */
    public function setFormatter($class_name, callable $func)
    {
        $this->config->formatters[$class_name] = $func;
    }


    /**
     * Remove the formatter function for a given class.
     *
     * @param string $class_name The class to remove the formatter from
     */
    public function removeFormatter($class_name)
    {
        unset($this->config->formatters[$class_name]);
    }


    /**
     * Sets an overriding prefix to prepend to an individual table.
     *
     * @param string $table The table to use a specific prefix for, e.g. 'pages'
     * @param string $prefix The prefix to use, e.g. 'temp_only_'
     */
    public function setTablePrefixOverride(string $table, string $prefix)
    {
        $this->config->table_prefixes[$table] = $prefix;
    }


    /**
     * Set an override PDO connection.
     *
     * @param PDO $connection
     * @return void
     */
    public function setOverrideConnection(PDO $connection)
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->override_connection = $connection;
    }


    /**
     * Clear any overridden connection, reverting behaviour back to
     * the default connection logic.
     */
    public function clearOverrideConnection()
    {
        $this->override_connection = null;
    }


    // ===========================================================
    //     Query builder
    // ===========================================================


    /**
     * Gets a PDO connection, creating a new one if necessary
     *
     * @return PDO
     * @throws PDOException If connection fails
     */
    public function getConnection()
    {
        if (isset($this->override_connection)) {
            return $this->override_connection;
        }

        if (isset($this->connection)) {
            return $this->connection;
        }

        $this->connection = self::connect($this->config);
        return $this->connection;
    }


    public function getPrefix(): string
    {
        return $this->config->prefix;
    }


    /**
     * Alias for {@see Pdb::query}
     **/
    public function q($query, array $params, $return_type)
    {
        return $this->query($query, $params, $return_type);
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
     * @param string $return_type 'pdo', 'count', 'null', or a format type {@see Pdb::formatRs}
     * @return array|string|int|null|PDOStatement
     * @throws InvalidArgumentException If $query isn't a string
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     */
    public function query(string $query, array $params, string $return_type)
    {
        $st = $this->prepare($query);
        return $this->execute($st, $params, $return_type);
    }


    /**
     * Prepare a query into a prepared statement
     *
     * @param string $query The query to execute. Prefix a table name with a tilde (~) to automatically include the
     *        table prefix, e.g. ~pages will be converted to fwc_pages
     * @return PDOStatement The prepared statement, for execution with {@see Pdb::execute}
     * @throws QueryException If the query execution or formatting failed
     */
    public function prepare(string $query) {
        $pdo = $this->getConnection();
        $query = $this->insertPrefixes($pdo, $query);

        try {
            return $pdo->prepare($query);
        } catch (PDOException $ex) {
            $ex = QueryException::create($ex)
                ->setQuery($query);
        }
    }


    /**
     * Executes a prepared statement
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
     * @param PDOStatement $st The query to execute. Prepare using {@see Pdb::prepare}
     * @param array $params Parameters to bind to the query
     * @param string $return_type 'pdo', 'count', 'null', or a format type {@see Pdb::formatRs}
     * @return PDOStatement For type 'pdo'
     * @return int For type 'count'
     * @return null For type 'null'
     * @return mixed For all other types; see {@see Pdb::formatRs}
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     */
    public function execute(PDOStatement $st, array $params, string $return_type)
    {
        self::validateReturnType($return_type);

        // Format objects into strings
        foreach ($params as &$p) {
            $p = $this->format($p);
        }
        unset($p);

        $ex = null;
        try {
            static::bindParams($st, $params);
            $st->execute();
            $res = $st;
            $count = $res->rowCount();

            // Save the insert ID specifically, so it doesn't get clobbered by IDs generated by logging
            if (stripos($st->queryString, 'INSERT') === 0) {
                $pdo = $this->getConnection();
                $this->last_insert_id = $pdo->lastInsertId();
            }

        } catch (PDOException $ex) {
            $ex = QueryException::create($ex)
                ->setParams($params)
                ->setQuery($st->queryString);
        }

        // The log method needs to be disabled so log functions
        // (that use pdb to create logs) can make queries without
        // logging themselves.
        $this->logQuery($st->queryString, $params, $ex ?? $res);

        // This is thrown after logging
        if ($ex) throw $ex;

        // PDO returns must not prematurely close the cursor.
        if ($return_type == self::RETURN_PDO) {
            $res->setFetchMode(PDO::FETCH_ASSOC);
            return $res;
        }

        try {
            $ret = self::formatRs($res, $return_type);
        } catch (RowMissingException $ex) {
            $res->closeCursor();
            $ex->setQuery($st->queryString);
            $ex->setParams($params);
            throw $ex;
        }
        $res->closeCursor();
        $res = null;
        return $ret;
    }


    // ===========================================================
    //     Core queries
    // ===========================================================


    /**
     * Return all columns for a single row of a table.
     * The row is specified using its id.
     *
     * @param string $table The table name, not prefixed
     * @param int $id The id of the record to fetch
     * @return array The record data
     * @throws QueryException If the query fails
     * @throws RowMissingException If there's no row
     */
    public function get(string $table, $id)
    {
        self::validateIdentifier($table);

        $q = "SELECT * FROM ~{$table} WHERE id = ?";
        return $this->query($q, [(int) $id], 'row');
    }


    /**
     * Runs an INSERT query
     *
     * @param string $table The table (without prefix) to insert the data into
     * @param array $data Data to insert, column => value
     * @return int The id of the newly-inserted record, if applicable
     * @throws InvalidArgumentException
     * @throws QueryException
     */
    public function insert($table, array $data)
    {
        self::validateIdentifier($table);
        if (count($data) == 0) {
            $err = 'An INSERT must set at least 1 column';
            throw new InvalidArgumentException($err);
        }

        $q = "INSERT INTO ~{$table}";

        $cols = '';
        $values = '';
        $insert = [];
        foreach ($data as $col => $val) {
            self::validateIdentifier($col);
            if ($cols) $cols .= ', ';
            $cols .= $this->quote($col, self::QUOTE_FIELD);
            if ($values) $values .= ', ';
            $values .= ":{$col}";
            $insert[":{$col}"] = $val;
        }
        $q .= " ({$cols}) VALUES ({$values})";

        $this->query($q, $insert, 'count');
        return $this->last_insert_id;
    }


    /**
     * Runs an UPDATE query
     * @param string $table The table (without prefix) to insert the data into
     * @param array $data Data to update, column => value
     * @param array $conditions Conditions for updates. {@see Pdb::buildClause}
     * @return int The number of affected rows
     * @throws InvalidArgumentException
     * @throws QueryException
     */
    public function update(string $table, array $data, array $conditions)
    {
        self::validateIdentifier($table);
        if (count($data) == 0) {
            $err = 'An UPDATE must apply to at least 1 column';
            throw new InvalidArgumentException($err);
        }
        if (count($conditions) == 0) {
            $err = 'An UPDATE requires at least 1 condition';
            throw new InvalidArgumentException($err);
        }

        $q = "UPDATE ~{$table} SET ";

        $cols = '';
        $values = [];
        foreach ($data as $col => $val) {
            self::validateIdentifier($col);
            if ($cols) $cols .= ', ';
            $cols .= "{$col} = ?";
            $values[] = $val;
        }
        $q .= $cols;

        $q .= " WHERE " . self::buildClause($conditions, $values);

        return $this->query($q, $values, 'count');
    }


    /**
     *
     * @param string $table
     * @param array $data
     * @param array $conditions
     * @return int
     * @throws InvalidArgumentException
     * @throws QueryException
     */
    public function upsert(string $table, array $data, array $conditions)
    {
        self::validateIdentifier($table);

        try {
            $params = [];
            $clause = self::buildClause($conditions, $params);
            $id = $this->query("SELECT id from {$table} WHERE {$clause}", $params, 'val');

            $this->update($table, $data, ['id' => $id]);
            return $id;
        }
        catch (RowMissingException $exception) {
            return $this->insert($table, $data);
        }
    }


    /**
     * Runs a DELETE query
     * @param string $table The table (without prefix) to insert the data into
     * @param array $conditions Conditions for updates. {@see Pdb::buildClause}
     * @return int The number of affected rows
     * @throws InvalidArgumentException
     * @throws QueryException
     */
    public function delete(string $table, array $conditions)
    {
        self::validateIdentifier($table);
        if (count($conditions) == 0) {
            $err = 'A DELETE requires at least 1 condition';
            throw new InvalidArgumentException($err);
        }

        $values = [];
        $q = "DELETE FROM ~{$table} WHERE ";
        $q .= self::buildClause($conditions, $values);
        return $this->query($q, $values, 'count');
    }


    /**
     * Shorthand for creating a new PdbQuery.
     *
     * @param mixed $string
     * @param mixed $table
     * @param array $conditions
     * @return PdbQuery
     */
    public function find(string $table, array $conditions = [])
    {
        return (new PdbQuery($this))->find($table, $conditions);
    }


    // ===========================================================
    //     Transactions
    // ===========================================================


    /**
     * Checks if there's a current transaction in progress
     * @return bool True if inside a transaction
     */
    public function inTransaction()
    {
        return $this->in_transaction;
    }


    /**
     * Starts a transaction
     * @return void
     * @throws TransactionRecursionException if already in a transaction
     */
    public function transact()
    {
        if ($this->in_transaction) {
            throw new TransactionRecursionException();
        }

        $pdo = $this->getConnection();
        $pdo->beginTransaction();
        $this->in_transaction = true;
    }


    /**
     * Commits a transaction
     * @return void
     */
    public function commit()
    {
        $pdo = $this->getConnection();
        $pdo->commit();
        $this->in_transaction = false;
    }


    /**
     * Rolls a transaction back
     * @return void
     */
    public function rollback()
    {
        $pdo = $this->getConnection();
        $pdo->rollBack();
        $this->in_transaction = false;
    }


    // ===========================================================
    //     Extended queries
    // ===========================================================


    /**
     * Fetches a mapping of id => value values from a table, using the 'name' values by default
     *
     * @deprecated Use PdbQuery::map()
     *
     * @param string $table The table name, without prefix
     * @param array $conditions Optional where clause {@see Pdb::buildClause}
     * @param array $order Optional columns to ORDER BY. Defaults to 'name'
     * @param string $name The field to use for the mapped values
     * @return array A lookup table
     **/
    public function lookup(string $table, array $conditions = [], array $order = ['name'], $name = 'name')
    {
        return (new PdbQuery($this))
            ->find($table, $conditions)
            ->orderBy(...$order)
            ->map('id', $name);
    }


    /**
     * Check to see that at least one record exists for certain conditions.
     *
     * @param string $table The table name, not prefixed
     * @param array $conditions Conditions for the WHERE clause, formatted as per {@see Pdb::buildClause}
     * @return bool True if a matching record exists
     * @throws QueryException If the query fails
     * @example if (!Pdb::recordExists('users', ['id' => 123, 'active' => 1])) {
     *     // ...
     * }
     */
    public function recordExists(string $table, array $conditions)
    {
        $count = (new PdbQuery($this))
            ->count($table, $conditions);

        return $count !== 0;
    }


    // ===========================================================
    //     Driver specific methods
    // ===========================================================


    /**
     * Fetches the current list of tables
     *
     * @return string[] Each element is a table name (with the prefix removed)
     */
    public abstract function listTables();


    /**
     *
     * @param string $table
     * @return bool
     */
    public abstract function tableExists(string $table);


    /**
     *
     * @param string $table
     * @return PdbIndex[]
     */
    public abstract function indexList(string $table);


    /**
     *
     * @param string $table
     * @return PdbColumn[]
     */
    public abstract function fieldList(string $table);


    /**
     * Gets all of the columns which have foreign key constraints in a table
     *
     * @param string $table The table to check for columns which link to other tables
     * @return PdbForeignKey[]
     */
    public abstract function getForeignKeys(string $table);



    /**
     * Gets all of the dependent foreign key columns (i.e. with the CASCADE delete rule) in other tables
     * which link to the id column of a specific table
     *
     * @param string $table The table which contains the id column which the foreign key columns link to
     * @return PdbForeignKey[]
     */
    public abstract function getDependentKeys(string $table);



    /**
     * Returns definition list from column of type ENUM
     *
     * @param string $table The DB table name, without prefix
     * @param string $column The column name
     * @return string[]
     */
    public abstract function extractEnumArr(string $table, string $column);


    // ===========================================================
    //     Generic helpers + validators
    // ===========================================================


    /**
     * Gets a datetime value for the current time.
     *
     * This is used to implement MySQL's NOW() function in PHP, but may change
     * if the decision is made to use INT columns instead of DATETIMEs. This
     * will probably happen at some point, so this function should only be used
     * for generating values right before an INSERT or UPDATE query is run
     *
     * @return string
     */
    public static function now()
    {
        return date('Y-m-d H:i:s');
    }


    /**
     * Return the value from the autoincement of the most recent INSERT query
     *
     * @return int The record id
     * @return null If there hasn't been an insert yet
     */
    public function getLastInsertId()
    {
        return $this->last_insert_id;
    }


    /**
     *
     * @param string $field
     * @param string $type
     * @return string
     * @throws PDOException
     */
    public function quote(string $field, string $type = self::QUOTE_VALUE): string
    {
        $pdo = $this->getConnection();

        // Integers are valid column names, so we escape them all the same.
        if (is_int($field)) {
            return $pdo->quote($field, PDO::PARAM_INT);
        }

        // Escape fields.
        if ($type === self::QUOTE_FIELD) {
            [$left, $right] = $this->getFieldQuotes($pdo);
            $field = trim($field, $left . $right);

            $parts = explode('.', $field, 2);
            foreach ($parts as &$part) {
                $part = "{$left}{$part}{$right}";
            }
            unset($part);
            return implode('.', $parts);
        }

        // Catch all.
        return $pdo->quote($field, PDO::PARAM_STR);
    }



    /**
     * Validate the return type.
     *
     * @param string $return_type
     * @return void
     * @throws InvalidArgumentException
     */
    public static function validateReturnType(string $return_type)
    {
        $return_type = strtolower($return_type);

        if (!in_array($return_type, self::RETURN_TYPES)) {
            $err = "Invalid \$return_type '{$return_type}'; see documentation";
            throw new InvalidArgumentException($err);
        }
    }


    /**
     * Validates a identifier (column name, table name, etc)
     *
     * @param string $name The identifier to check
     * @return void
     * @throws InvalidArgumentException If the identifier is invalid
     */
    public static function validateIdentifier($name)
    {
        if (!preg_match('/^[a-z_][a-z_0-9]*$/i', $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
    }


    /**
     * Validates a identifier in extended format -- table.column
     * Also accepts short format like {@see validateIdentifier} does.
     *
     * @param string $name The identifier to check
     * @return void
     * @throws InvalidArgumentException If the identifier is invalid
     */
    public static function validateIdentifierExtended($name)
    {
        if (!preg_match('/^(?:[a-z_][a-z_0-9]*\.)?[a-z_][a-z_0-9]*$/i', $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
    }


    /**
     * Validates a value meant for an ENUM field, e.g.
     * $valid->addRules('col1', 'required', 'Pdb::validateEnum[table, col]');
     *
     * @param string $val The value to find in the ENUM
     * @param array $field [0] Table name [1] Column name
     * @return bool
     */
    public function validateEnum(string $val, array $field)
    {
        list($table, $col) = $field;
        $enum = $this->extractEnumArr($table, $col);
        if (count($enum) == 0) return false;
        if (in_array($val, $enum)) return true;
        return false;
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
     *
     * Conditions are usually combined using AND, but can also be OR or XOR; see the $combine parameter.
     *
     * @param array $conditions
     * Conditions for the clause. Each condition is either:
     * - The scalar value 1 (to match ALL records -- BEWARE if using the clause in an UPDATE or DELETE)
     * - The scalar value 0 (to match no records)
     * - A column => value pair for an '=' condition.
     *   For example: 'id' => 3
     * - An array with three elements: [column, operator, value(s)]
     *   For example:
     *       ['id', '=', 3]
     *       ['date_added', 'BETWEEN', ['2010', '2015']]
     *       ['status', 'IN', ['ACTIVE', 'APPROVE']]
     *   Simple operators:
     *       =  <=  >=  <  >  !=  <>
     *   Operators for LIKE conditions; escaping of characters like % is handled automatically:
     *       CONTAINS  string
     *       BEGINS    string
     *       ENDS      string
     *   Other operators:
     *       IS        string 'NULL' or 'NOT NULL'
     *       BETWEEN   array of 2 values
     *       (NOT) IN  array of values
     *       IN SET    string -- note the order matches other operators; ['column', 'IN SET', 'val1,ca']
     * @param array $values Array of bind parameters. Additional parameters will be appended to this array
     * @param string $combine String to be placed between the conditions. Must be one of: 'AND', 'OR', 'XOR'
     * @return string A clause which is safe to use in a prepared SQL statement
     * @example
     * $conditions = ['active' => 1, ['date_added', 'BETWEEN', ['2015-01-01', '2016-01-01']]];
     * $params = [];
     * $where = Pdb::buildClause($conditions, $params);
     * //
     * // Variable contents:
     * // $where == "active = ? AND date_added BETWEEN ? AND ?"
     * // $params == [1, '2015-01-01', '2016-01-01'];
     * //
     * $q = "SELECT * FROM ~my_table WHERE {$where}";
     * $res = Pdb::query($q, $params, 'pdo');
     * foreach ($res as $row) {
     *     // Record processing here
     * }
     * $res->closeCursor();
     */
    public static function buildClause(array $conditions, array &$values, $combine = 'AND')
    {
        if ($combine != 'AND' and $combine != 'OR' and $combine != 'XOR') {
            throw new InvalidArgumentException('Combine parameter must be of of: "AND", "OR", "XOR"');
        }
        $combine = " {$combine} ";

        $where = '';
        foreach ($conditions as $key => $cond) {
            if ($where) $where .= $combine;
            if (is_scalar($cond) or is_null($cond)) {
                if (preg_match('/^[0-9]+$/', $key)) {
                    $cond = (string) $cond;
                    if ($cond != '1' and $cond != '0') {
                        $err = '1 and 0 are the only accepted scalar conditions';
                        throw new InvalidArgumentException($err);
                    }
                    $where .= $cond;
                } else {
                    self::validateIdentifierExtended($key);
                    if (is_null($cond)) {
                        $where .= "{$key} IS NULL";
                    } else {
                        $where .= "{$key} = ?";
                        $values[] = $cond;
                    }
                }
                continue;
            }

            if (!is_array($cond)) {
                throw new InvalidArgumentException('Condition must be scalar, array, or null - not ' . gettype($cond));
            }

            if (count($cond) != 3) {
                $err = 'An array condition needs exactly 3 elements: ';
                $err .= 'column, operator, value(s); ' . count($cond) . ' provided';
                throw new InvalidArgumentException($err);
            }
            list($col, $op, $val) = $cond;
            self::validateIdentifierExtended($col);

            switch ($op) {
            case '=':
            case '<=':
            case '>=':
            case '<':
            case '>':
            case '!=':
            case '<>':
                if (!is_scalar($val)) {
                    $err = "Operator {$op} needs a scalar value";
                    throw new InvalidArgumentException($err);
                }
                $where .= "{$col} {$op} ?";
                $values[] = $val;
                break;

            case 'IS':
                if ($val === null) $val = 'NULL';
                if ($val == 'NULL' or $val == 'NOT NULL') {
                    $where .= "{$col} {$op} {$val}";
                } else {
                    $err = "Operator IS value must be NULL or NOT NULL";
                    throw new InvalidArgumentException($err);
                }
                break;

            case 'BETWEEN':
                $err = "Operator BETWEEN value must be an array of two scalars";
                if (!is_array($val)) {
                    throw new InvalidArgumentException($err);
                } else if (count($val) != 2 or !is_scalar($val[0]) or !is_scalar($val[1])) {
                    throw new InvalidArgumentException($err);
                }
                $where .= "{$col} BETWEEN ? AND ?";
                $values[] = $val[0];
                $values[] = $val[1];
                break;

            case 'IN':
            case 'NOT IN';
                $err = "Operator {$op} value must be an array of scalars";
                if (!is_array($val)) {
                    throw new InvalidArgumentException($err);
                } else {
                       foreach ($val as $idx => $v) {
                        if (!is_scalar($v)) {
                            throw new InvalidArgumentException($err . " (index {$idx})");
                        }
                    }
                }
                $where .= "{$col} {$op} (" . rtrim(str_repeat('?, ', count($val)), ', ') . ')';
                foreach ($val as $v) {
                    $values[] = $v;
                }
                break;

            case 'CONTAINS':
                $where .= "{$col} LIKE CONCAT('%', ?, '%')";
                $values[] = PdbHelpers::likeEscape($val);
                break;

            case 'BEGINS':
                $where .= "{$col} LIKE CONCAT(?, '%')";
                $values[] = PdbHelpers::likeEscape($val);
                break;

            case 'ENDS':
                $where .= "{$col} LIKE CONCAT('%', ?)";
                $values[] = PdbHelpers::likeEscape($val);
                break;

            case 'IN SET':
                $where .= "FIND_IN_SET(?, {$col}) > 0";
                $values[] = PdbHelpers::likeEscape($val);
                break;

            default:
                $err = 'Operator not implemented: ' . $op;
                throw new InvalidArgumentException($err);
            }
        }
        return $where;
    }


    /**
     * Converts a PDO result set to a common data format:
     *
     * arr      An array of rows, where each row is an associative array.
     *          Use only for very small result sets, e.g. <= 20 rows.
     *
     * arr-num  An array of rows, where each row is a numeric array.
     *          Use only for very small result sets, e.g. <= 20 rows.
     *
     * row      A single row, as an associative array
     *
     * row-num  A single row, as a numeric array
     *
     * map      An array of identifier => value pairs, where the
     *          identifier is the first column in the result set, and the
     *          value is the second
     *
     * map-arr  An array of identifier => value pairs, where the
     *          identifier is the first column in the result set, and the
     *          value an associative array of name => value pairs
     *          (if there are multiple subsequent columns)
     *
     * val      A single value (i.e. the value of the first column of the
     *          first row)
     *
     * col      All values from the first column, as a numeric array.
     *          DO NOT USE with boolean columns; see note at
     *          http://php.net/manual/en/pdostatement.fetchcolumn.php
     *
     * @param string $type One of 'arr', 'arr-num', 'row', 'row-num', 'map', 'map-arr', 'val' or 'col'
     * @return array For most types
     * @return string For 'val'
     * @throws RowMissingException If the result set didn't contain the required row
     */
    protected static function formatRs(PDOStatement $rs, string $type)
    {
        switch ($type) {
        case 'null':
            return null;
            break;

        case 'count':
            return $rs->rowCount();
            break;

        case 'arr':
            return $rs->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'arr-num':
            return $rs->fetchAll(PDO::FETCH_NUM);
            break;

        case 'row':
            $row = $rs->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RowMissingException('Expected a row');
            return $row;
            break;

        case 'row-num':
            $row = $rs->fetch(PDO::FETCH_NUM);
            if (!$row) throw new RowMissingException('Expected a row');
            return $row;
            break;

        case 'map':
            if ($rs->columnCount() < 2) {
                throw new InvalidArgumentException('Two columns required');
            }
            $map = array();
            while ($row = $rs->fetch(PDO::FETCH_NUM)) {
                $map[$row[0]] = $row[1];
            }
            return $map;
            break;

        case 'map-arr':
            $map = array();
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $id = reset($row);
                $map[$id] = $row;
            }
            return $map;
            break;

        case 'val':
            $row = $rs->fetch(PDO::FETCH_NUM);
            if (!$row) throw new RowMissingException('Expected a row');
            return $row[0];
            break;

        case 'col':
            $arr = [];
            while (($col = $rs->fetchColumn(0)) !== false) {
                $arr[] = $col;
            }
            return $arr;
            break;

        default:
            $err = "Unknown return type: {$type}";
            throw new InvalidArgumentException($err);
        }
    }


    // ===========================================================
    //     Internal helpers
    // ===========================================================


    /**
     * Format a object in accordance to the registered formatters.
     *
     * Formatters convert objects to saveable types, such as a string or an integer
     *
     * @param mixed $value The value to format
     * @return string The formatted value
     * @throws InvalidArgumentException
     */
    protected function format($value)
    {
        if (!is_object($value)) return $value;

        $class_name = get_class($value);
        $func = $this->config->formatters[$class_name] ?? null;

        if ($func === null) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            throw new InvalidArgumentException("Unable to format objects of type '{$class_name}'");
        }

        $value = $func($value);
        if (!is_string($value) and !is_int($value)) {
            throw new InvalidArgumentException("Formatter for type '{$class_name}' must return a string or int");
        }

        return $value;
    }


    /**
     * Get parameter quotes as appropriate for the underlying DBMS.
     *
     * For things like fields, tables, etc.
     *
     * @param PDO $pdo
     * @return string[] [left, right]
     */
    protected function getFieldQuotes(PDO $pdo)
    {
        switch ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $lquote = $rquote = '`';
                break;

            case 'mssql':
                $lquote = '[';
                $rquote = ']';
                break;

            case 'sqlite':
            case 'pgsql':
            default:
                $lquote = $rquote = '"';
        }

        return [$lquote, $rquote];
    }


    /**
     * Replaces tilde placeholders with table prefixes, and quotes tables according to the rules of the underlying DBMS
     *
     * @param PDO $pdo The database connection, for determining table quoting rules
     * @param string $query Query which contains tilde placeholders, e.g. 'SELECT * FROM ~pages WHERE id = 1'
     * @return string Query with tildes replaced by prefixes, e.g. 'SELECT * FROM `fwc_pages` WHERE id = 1'
     */
    protected function insertPrefixes(PDO $pdo, string $query)
    {
        [$lquote, $rquote] = $this->getFieldQuotes($pdo);

        $replacer = function(array $matches) use ($lquote, $rquote) {
            $prefix = $this->config->table_prefixes[$matches[1]] ?? $this->config->prefix;
            return $lquote . $prefix . $matches[1] . $rquote;
        };

        return preg_replace_callback('/\~([\w0-9_]+)/', $replacer, $query);
    }


    /**
     *
     * @param string $value
     * @return string
     */
    protected function stripPrefix(string $value)
    {
        static $patterns = [];

        $pattern = $patterns[$this->config->prefix] ?? null;
        if (!$pattern) {
            $pattern = '/^' . preg_quote($this->config->prefix, '/') . '/';
            $patterns[$this->config->prefix] = $pattern;
        }

        return preg_replace($pattern, '', $value) ?? '';
    }


    /**
     * Bind the array of parameters to a PDO statement
     *
     * Unlike PDOStatement::execute which binds everything as PARAM_STR,
     * this method will bind integers as PARAM_INT
     *
     * @param PDOStatement $st Statement to bind parameters to
     * @param array $params Parameters to bind
     */
    protected static function bindParams(PDOStatement $st, array $params)
    {
        foreach ($params as $key => $val) {
            // Numeric (question mark) params require 1-based indexing
            if (!is_string($key)) {
                $key += 1;
            }

            if (is_int($val)) {
                $st->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $st->bindValue($key, $val, PDO::PARAM_STR);
            }
        }
    }


    /**
     * Log something.
     *
     * @param string $query
     * @param array $params
     * @param PDOStatement|QueryException $result
     * @return void
     */
    protected function logQuery(string $query, array $params, $result)
    {
        $query .= ' with ' . json_encode($params);
        $this->log($query, Log::LEVEL_DEBUG);

        if ($result instanceof QueryException) {
            $this->log($result, Log::LEVEL_ERROR);
        }
    }


    /**
     * Gets the subset of bind params which are associated with a particular query from a generic list of bind params.
     * This is used to support the SQL DB tool.
     * N.B. This probably won't work if you mix named and numbered params in the same query.
     *
     * @param string $q
     * @param array $binds generic list of binds
     * @return array
     */
    public static function getBindSubset(string $q, array $binds)
    {
        $q = PdbHelpers::stripStrings($q);

        // Strip named params which aren't required
        // N.B. identifier format matches self::validateIdentifier
        $params = [];
        preg_match_all('/:[a-z_][a-z_0-9]*/i', $q, $params);
        $params = $params[0];
        foreach ($binds as $key => $val) {
            if (is_int($key)) continue;

            if (count($params) == 0) {
                unset($binds[$key]);
                continue;
            }

            $required = false;
            foreach ($params as $param) {
                if ($key[0] == ':') {
                    if ($param[0] != ':') {
                        $param = ':' . $param;
                    }
                } else {
                    $param = ltrim($param, ':');
                }
                if ($key == $param) {
                    $required = true;
                }
            }
            if (!$required) {
                unset($binds[$key]);
            }
        }

        // Strip numbered params which aren't required
        $params = [];
        preg_match_all('/\?/', $q, $params);
        $params = $params[0];
        if (count($params) == 0) {
            foreach ($binds as $key => $bind) {
                if (is_int($key)) {
                    unset($binds[$key]);
                }
            }
            return $binds;
        }

        foreach ($binds as $key => $val) {
            if (!is_int($key)) unset($binds[$key]);
        }
        while (count($params) < count($binds)) {
            array_pop($binds);
        }

        return $binds;
    }


    /**
     * Generates a backtrace, and searches it to find the point at which a query was called
     * @return array The trace entry in which the query was called
     * @return false If a query call couldn't be found in the trace
     */
    private static function backtraceQuery() {
        $trace = debug_backtrace();
        $caller = null;
        while ($step = array_pop($trace)) {
            if (@$step['class'] == static::class) {
                // Provide calling step, as it's useful if the current step
                // doesn't provide file and line num.
                $step['caller'] = $caller;
                return $step;
            }
            $caller = $step;
        }
        return false;
    }
}
