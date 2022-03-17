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
use karmabunny\kb\Uuid;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Exceptions\RowMissingException;
use karmabunny\pdb\Exceptions\TransactionRecursionException;
use karmabunny\pdb\Drivers\PdbMysql;
use karmabunny\pdb\Drivers\PdbNoDriver;
use karmabunny\pdb\Drivers\PdbPgsql;
use karmabunny\pdb\Drivers\PdbSqlite;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbCondition;
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

    const RETURN_TRY_VAL = 'val?';

    const RETURN_TRY_ROW = 'row?';

    const RETURN_TRY_ROW_NUM = 'row-num?';

    const RETURN_NULLABLE = [
        self::RETURN_TRY_VAL,
        self::RETURN_TRY_ROW,
        self::RETURN_TRY_ROW_NUM,
    ];

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

    /** @var bool */
    protected $in_transaction = false;

    /** @var int|null */
    protected $last_insert_id = null;

    /** @var callable|null (query, params, result|exception) */
    protected $debugger;

    /** @var string */
    private $_prefix_pattern;


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

        $this->_prefix_pattern = '/^' . preg_quote($this->config->prefix, '/') . '/';

        // The config provides an override connection.
        if ($this->config->_pdo instanceof PDO) {
            $this->connection = $this->config->_pdo;
        }
    }


    /**
     *
     * @param PdbConfig|array $config
     * @return Pdb
     */
    public static function create($config)
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        switch ($config->type) {
            case PdbConfig::TYPE_MYSQL:
                return new PdbMysql($config);

            case PdbConfig::TYPE_PGSQL:
                return new PdbPgsql($config);

            case PdbConfig::TYPE_SQLITE:
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
     * @throws ConnectionException
     */
    public static function connect($config)
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        try {
            $pdo = new PDO($config->getDsn(), $config->user, $config->pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $exception) {
            throw ConnectionException::create($exception)
                ->setDsn($config->getDsn());
        }
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
     * Remove a log callback.
     *
     * The index is returned from addLogger().
     *
     * TODO This should live in the kbphp - LoggerTrait.
     *
     * @param int $index
     * @return void
     */
    public function removeLogger(int $index)
    {
        unset($this->loggers[$index]);
    }


    /**
     * Remove all loggers.
     *
     * Note, this does not remove the debug logger.
     *
     * @return void
     */
    public function clearLoggers()
    {
        $this->loggers = [];
    }


    /**
     * Attach/remove a debug logger.
     *
     * This receives more detail than the loggable trait.
     *
     * @param callable|null $debugger log method:
     *  - string $query
     *  - array $params
     *  - PDOStatement|QueryException $result
     * @return void
     */
    public function setDebugger($debugger)
    {
        $this->debugger = $debugger;
    }


    // ===========================================================
    //     Query builder
    // ===========================================================


    /**
     * Gets a PDO connection, creating a new one if necessary
     *
     * @return PDO
     * @throws ConnectionException If connection fails
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            $this->connection = static::connect($this->config);
        }

        return $this->connection;
    }


    public function getPrefix(): string
    {
        return $this->config->prefix;
    }


    /**
     * Alias for {@see Pdb::query}
     *
     * @param string $query The query to execute. Prefix a table name with a tilde (~) to automatically include the
     *        table prefix, e.g. ~pages will be converted to fwc_pages
     * @param array $params Parameters to bind to the query
     * @param string $return_type 'pdo', 'count', 'null', or a format type {@see Pdb::formatRs}
     * @return array|string|int|null|PDOStatement
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     * @throws ConnectionException If the connection fails
     */
    public function q($query, array $params, string $return_type)
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
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     * @throws ConnectionException If the connection fails
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
     * @throws ConnectionException If the connection fails
     */
    public function prepare(string $query) {
        $pdo = $this->getConnection();
        $query = $this->insertPrefixes($query);

        try {
            return $pdo->prepare($query);
        } catch (PDOException $ex) {
            throw QueryException::create($ex)
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
     * Return types:
     * - PDOStatement For type 'pdo'
     * - int For type 'count'
     * - null For type 'null'
     * - mixed For all other types; see {@see Pdb::formatRs}
     *
     * @param PDOStatement $st The query to execute. Prepare using {@see Pdb::prepare}
     * @param array $params Parameters to bind to the query
     * @param string $return_type 'pdo', 'count', 'null', or a format type {@see Pdb::formatRs}
     * @return array|string|int|null|PDOStatement
     * @throws InvalidArgumentException If the return type isn't valid
     * @throws QueryException If the query execution or formatting failed
     * @throws ConnectionException If the connection fails
     */
    public function execute(PDOStatement $st, array $params, string $return_type)
    {
        static::validateReturnType($return_type);

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

            // Save the insert ID specifically, so it doesn't get clobbered by IDs generated by logging
            if (stripos($st->queryString, 'INSERT') === 0) {
                $pdo = $this->getConnection();
                $this->last_insert_id = (int) $pdo->lastInsertId();
            }

            $this->logQuery($st->queryString, $params, $res);

        } catch (PDOException $ex) {
            $ex = QueryException::create($ex)
                ->setParams($params)
                ->setQuery($st->queryString);

            $this->logQuery($st->queryString, $params, $ex);

            throw $ex;
        }

        // PDO returns must not prematurely close the cursor.
        if ($return_type == self::RETURN_PDO) {
            $res->setFetchMode(PDO::FETCH_ASSOC);
            return $res;
        }

        try {
            $ret = static::formatRs($res, $return_type);
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
     * $where = $pdb->buildClause($conditions, $params);
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
    public function buildClause(array $conditions, array &$values, $combine = 'AND')
    {
        if ($combine != 'AND' and $combine != 'OR' and $combine != 'XOR') {
            throw new InvalidArgumentException('Combine parameter must be of of: "AND", "OR", "XOR"');
        }

        $conditions = PdbCondition::fromArray($conditions);
        $combine = " {$combine} ";
        $where = '';

        foreach ($conditions as $condition) {
            $clause = $condition->build($this, $values);
            if (!$clause) continue;

            if ($where) $where .= $combine;
            $where .= $clause;
        }

        return $where;
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
     * @throws InvalidArgumentException
     * @throws ConnectionException
     */
    public function get(string $table, $id)
    {
        static::validateIdentifier($table);

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
     * @throws ConnectionException
     */
    public function insert($table, array $data)
    {
        static::validateIdentifier($table);

        if (count($data) == 0) {
            $err = 'An INSERT must set at least 1 column';
            throw new InvalidArgumentException($err);
        }

        $columns = [];
        $values = [];

        foreach ($data as $col => $val) {
            static::validateIdentifier($col);
            $columns[] = $col;
            $values[] = $val;
        }

        $columns = implode(', ', $this->quoteAll($columns, Pdb::QUOTE_FIELD));
        $binds = PdbHelpers::bindPlaceholders(count($values));
        $q = "INSERT INTO ~{$table} ({$columns}) VALUES ({$binds})";

        $this->query($q, $values, 'count');
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
     * @throws ConnectionException
     */
    public function update(string $table, array $data, array $conditions)
    {
        static::validateIdentifier($table);
        if (count($data) == 0) {
            $err = 'An UPDATE must apply to at least 1 column';
            throw new InvalidArgumentException($err);
        }
        if (count($conditions) == 0) {
            $err = 'An UPDATE requires at least 1 condition';
            throw new InvalidArgumentException($err);
        }

        $cols = [];
        $values = [];

        foreach ($data as $col => $val) {
            static::validateIdentifier($col);
            $cols[] = $this->quoteField($col) . ' = ?';
            $values[] = $val;
        }

        $cols = implode(', ', $cols);

        $q = "UPDATE ~{$table} SET {$cols} WHERE ";
        $q .= $this->buildClause($conditions, $values);
        return $this->query($q, $values, 'count');
    }


    /**
     *
     * @param string $table
     * @param array $data
     * @param array $conditions
     * @param null|string $update
     * @return int
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function upsert(string $table, array $data, array $conditions, $update = null)
    {
        static::validateIdentifier($table);

        // OK so this uses 2 queries inside a transaction. It's as good as it gets here.
        // - Using ON DUPLICATE KEY UPDATE is incomplete without RETURNING (MariaDb 10.5, Postgres, Oracle)
        //   because otherwise we lose the LAST_INSERT_ID.
        // - Using OR REPLACE is also a no-go because it modifies the PK and there's
        //   no guarantee that all FKs have correct UPDATE triggers.

        // Create a transaction if one is not already active.
        $transact = $this->inTransaction();
        if (!$transact) $this->transact();

        try {
            $params = [];
            $clause = $this->buildClause($conditions, $params);
            $id = $this->query("SELECT id from {$table} WHERE {$clause}", $params, 'val');

            // Dynamic update.
            if ($update) {
                $this->query("UPDATE ~{$table} WHERE id = ?", [$id], 'pdo');
            }
            // Regular update.
            else {
                $this->update($table, $data, ['id' => $id]);
            }

            return $id;
        }
        catch (RowMissingException $exception) {
            return $this->insert($table, $data);
        }
        finally {
            // Only commit if it's our own transaction.
            if ($transact) $this->commit();
        }
    }


    /**
     * Runs a DELETE query
     * @param string $table The table (without prefix) to insert the data into
     * @param array $conditions Conditions for updates. {@see Pdb::buildClause}
     * @return int The number of affected rows
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function delete(string $table, array $conditions)
    {
        static::validateIdentifier($table);
        if (count($conditions) == 0) {
            $err = 'A DELETE requires at least 1 condition';
            throw new InvalidArgumentException($err);
        }

        $values = [];
        $q = "DELETE FROM ~{$table} WHERE ";
        $q .= $this->buildClause($conditions, $values);
        return $this->query($q, $values, 'count');
    }


    /**
     * Shorthand for creating a new PdbQuery.
     *
     * @param string $table
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
     * @throws ConnectionException If the connection fails
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
     * @throws ConnectionException If the connection fails
     * @throws PDOException
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
     * @throws ConnectionException If the connection fails
     * @throws PDOException
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
        return $this->find($table, $conditions)
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
        return (bool) $this->find($table, $conditions)->count();
    }


    // ===========================================================
    //     Driver specific methods
    // ===========================================================


    /**
     * Get the permissions of the current connection.
     *
     * @return string[]
     */
    public abstract function getPermissions();


    /**
     * Fetches the current list of tables.
     *
     * @return string[] non-prefixed
     */
    public abstract function listTables();


    /**
     *
     * @param string $table non-prefixed
     * @return bool
     */
    public abstract function tableExists(string $table);


    /**
     *
     * @param string $table non-prefixed
     * @return PdbIndex[]
     */
    public abstract function indexList(string $table);


    /**
     *
     * @param string $table non-prefixed
     * @return PdbColumn[] [ name => PdbColumn ]
     */
    public abstract function fieldList(string $table);


    /**
     * Gets all of the columns which have foreign key constraints in a table
     *
     * @param string $table non-prefixed
     * @return PdbForeignKey[]
     */
    public abstract function getForeignKeys(string $table);



    /**
     * Gets all of the dependent foreign key columns (i.e. with the CASCADE delete rule) in other tables
     * which link to the id column of a specific table
     *
     * @param string $table non-prefixed
     * @return PdbForeignKey[]
     */
    public abstract function getDependentKeys(string $table);


    /**
     * Get adapter specific table information.
     *
     * @param string $table non-prefixed
     * @return array key => value
     */
    public abstract function getTableAttributes(string $table);


    /**
     * Returns definition list from column of type ENUM
     *
     * @param string $table non-prefixed
     * @param string $column
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
     * @param string $type Pdb::QUOTE
     * @return string
     * @throws ConnectionException
     */
    public function quote(string $field, string $type): string
    {
        if ($type === self::QUOTE_FIELD) {
            return $this->quoteField($field);
        }
        else {
            return $this->quoteValue($field);
        }
    }


    /**
     * @param iterable<string> $fields
     * @param string $type Pdb::QUOTE
     * @return string[]
     * @throws ConnectionException
     */
    public function quoteAll($fields, string $type): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[] = $this->quote($field, $type);
        }
        return $values;
    }


    /**
     *
     * @param mixed $value
     * @return string
     * @throws ConnectionException
     */
    public function quoteValue($value): string
    {
        $pdo = $this->getConnection();

        if (is_numeric($value)) {
            $result = $pdo->quote($value, PDO::PARAM_INT);
            if ($result !== false) return $result;
        }

        if (is_bool($value)) {
            $result = $pdo->quote((string) $value, PDO::PARAM_BOOL);
            if ($result !== false) return $result;
        }

        return $pdo->quote($value, PDO::PARAM_STR);
    }


    /**
     *
     * @param string $field
     * @return string
     */
    public function quoteField(string $field): string
    {
        // Integer-ish fields are ok.
        // TODO At least for SELECT, everything else though?
        if (is_numeric($field) and (int) $field == (float) $field) {
            return $field;
        }

        $quotes = $this->config->getFieldQuotes();
        [$left, $right] = $quotes;

        $field = str_replace($quotes, '', $field);
        $parts = explode('.', $field, 2);

        foreach ($parts as &$part) {
            if (strpos($part, '~') === 0) continue;
            if ($part == '*') continue;
            $part = $left . trim($part, '\'"[]`') . $right;
        }
        unset($part);

        return implode('.', $parts);
    }


    /**
     * Create a UUIDv5 for this table + id.
     *
     * These are 'namespaced' UUIDs. Within this namespace we have a 'scheme'
     * that is built from the `database + table + id`. This means UUIDs are
     * unique _and_ reproducible.
     *
     * If the id is zero the UUID will be a nil.
     *
     * @param string $table
     * @param int $id
     * @return string
     */
    public function generateUid(string $table, int $id)
    {
        if ($id == 0) return Uuid::nil();

        ['database' => $database, 'prefix' => $prefix] = $this->config;
        $scheme = "{$database}.{$prefix}.{$table}.{$id}";
        return Uuid::uuid5(self::UUID_NAMESPACE, $scheme);
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
     * @param bool $loose Permit integers + functions - e.g. SELECT 1, COUNT(*), etc
     * @return void
     * @throws InvalidArgumentException If the identifier is invalid
     */
    public static function validateIdentifier($name, $loose = false)
    {
        if ($loose) {
            // Numbers are ok.
            if (is_numeric($name) and (int) $name == (float) $name) {
                return;
            }

            // Functions are kinda ok.
            // This is could be improved.
            if (preg_match(PdbHelpers::RE_FUNCTION, $name)) return;
        }

        if (!preg_match(PdbHelpers::RE_IDENTIFIER, $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
    }


    /**
     * Validates a identifier in extended format -- table.column
     * Also accepts short format like {@see validateIdentifier} does.
     *
     * @param string $name The identifier to check
     * @param bool $loose Permit integers + functions - e.g. SELECT 1, COUNT(*), etc
     * @return void
     * @throws InvalidArgumentException If the identifier is invalid
     */
    public static function validateIdentifierExtended($name, $loose = false)
    {
        if ($loose) {
            // Numbers are ok.
            if (is_numeric($name) and (int) $name == (float) $name) {
                return;
            }

            // Functions are kinda ok.
            // This is could be improved.
            if (preg_match(PdbHelpers::RE_FUNCTION, $name)) return;
        }

        if (!preg_match(PdbHelpers::RE_IDENTIFIER_EXTENDED, $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
    }


    /**
     * Validate an SQL function.
     *
     * @param string $value
     * @return void
     * @throws InvalidArgumentException
     */
    public static function validateFunction($value)
    {
        if (!preg_match(PdbHelpers::RE_FUNCTION, $value)) {
            throw new InvalidArgumentException("Invalid function: {$value}");
        }
    }


    /**
     * Validates an order-by direction.
     *
     * @param string $name The identifier to check
     * @return void
     * @throws InvalidArgumentException If the identifier is invalid
     */
    public static function validateDirection($name)
    {
        if (!in_array(strtoupper($name), ['DESC', 'ASC'])) {
            throw new InvalidArgumentException("Invalid direction: {$name}");
        }
    }


    /**
     * Validate an alias.
     *
     *
     * @param string|string[] $field
     * @param bool $loose Permit integers + functions - e.g. SELECT 1, COUNT(*), etc
     *   - Use for select(), do not use for tables or joins.
     * @return array [field, alias]
     *   - The second item is null if no alias present.
     *
     * @throws InvalidArgumentException
     */
    public static function validateAlias($field, $loose = false): array
    {
        if (is_string($field)) {
            // [$field, $alias] = PdbHelpers::alias($field);
            $alias = null;
        }
        else {
            if (count($field) != 2) {
                throw new InvalidArgumentException('Alias must have two elements: [name, alias]');
            }

            [$field, $alias] = $field;
        }

        // Only permit integer fields if there is no alias.
        // Pdb::validateIdentifierExtended($field, !$alias and $loose);

        Pdb::validateIdentifierExtended($field, $loose);

        if ($alias) {
            Pdb::validateIdentifier($alias);
        }

        return [$field, $alias];
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
     * Replaces tilde placeholders with table prefixes, and quotes tables according to the rules of the underlying DBMS
     *
     * @param string $query Query which contains tilde placeholders, e.g. 'SELECT * FROM ~pages WHERE id = 1'
     * @return string Query with tildes replaced by prefixes, e.g. 'SELECT * FROM `fwc_pages` WHERE id = 1'
     */
    public function insertPrefixes(string $query)
    {
        [$lquote, $rquote] = $this->config->getFieldQuotes();

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
        return preg_replace($this->_prefix_pattern, '', $value) ?? '';
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
     * @return string|int|null|array For 'val'
     * @throws RowMissingException If the result set didn't contain the required row
     */
    public static function formatRs(PDOStatement $rs, string $type)
    {
        $nullable = false;

        switch ($type) {
        case 'null':
            return null;

        case 'count':
            // Using SQL count() is always faster than rowCount().
            if (preg_match('/^\s*SELECT\s+COUNT\([1*]\)/i', $rs->queryString)) {
                $row = $rs->fetch(PDO::FETCH_NUM);
                return $row[0] ?? 0;
            }

            return $rs->rowCount();

        case 'arr':
            return $rs->fetchAll(PDO::FETCH_ASSOC);

        case 'arr-num':
            return $rs->fetchAll(PDO::FETCH_NUM);

        case 'row?':
            $nullable = true;
            // fall-through.

        case 'row':
            $row = $rs->fetch(PDO::FETCH_ASSOC);
            if (!$row and !$nullable) throw new RowMissingException('Expected a row');
            return $row;

        case 'row-num?':
            $nullable = true;
            // fall-through.

        case 'row-num':
            $row = $rs->fetch(PDO::FETCH_NUM);
            if (!$row and !$nullable) throw new RowMissingException('Expected a row');
            return $row;

        case 'map':
            if ($rs->columnCount() < 2) {
                throw new InvalidArgumentException('Two columns required');
            }
            $map = array();
            while ($row = $rs->fetch(PDO::FETCH_NUM)) {
                $map[$row[0]] = $row[1];
            }
            return $map;

        case 'map-arr':
            $map = array();
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $id = reset($row);
                $map[$id] = $row;
            }
            return $map;

        case 'val?':
            $nullable = true;
            // fall-through.

        case 'val':
            $row = $rs->fetch(PDO::FETCH_NUM);
            if (!$row and !$nullable) throw new RowMissingException('Expected a row');
            return $row[0];

        case 'col':
            $arr = [];
            while (($col = $rs->fetchColumn(0)) !== false) {
                $arr[] = $col;
            }
            return $arr;

        default:
            $err = "Unknown return type: {$type}";
            throw new InvalidArgumentException($err);
        }
    }


    /**
     * Log something.
     *
     * Be careful when attaching a logger that uses pdb to record logs. In this
     * case one should clone the pdb instance and remove any loggers on it
     * before using it to record logs.
     *
     * Such as:
     * ```
     * $log = clone $pdb;
     * $log->clearLoggers();
     *
     * $pdb->addLogger(function($message) use ($log) {
     *     $log->insert('sql_log', ['message' => $message]);
     * });
     * ```
     *
     * Note, this method will still use the same connection.
     *
     * @param string $query
     * @param array $params
     * @param PDOStatement|QueryException $result
     * @return void
     */
    protected function logQuery(string $query, array $params, $result)
    {
        if ($result instanceof QueryException) {
            $this->log($result, Log::LEVEL_ERROR);
        }
        else {
            $message = $query;

            if (!empty($params)) {
                $message .= ' with ' . json_encode($params);
            }

            $this->log($message, Log::LEVEL_DEBUG);
        }

        // Special debugger logger.
        // This is mostly for backwards compatibility.
        if (is_callable($this->debugger)) {
            $fn = $this->debugger;

            // The log method needs to be disabled so log functions can
            // make queries without logging themselves.
            $this->debugger = null;
            $fn($query, $params, $result);
            $this->debugger = $fn;
        }
    }
}
