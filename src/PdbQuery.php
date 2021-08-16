<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use Generator;
use InvalidArgumentException;
use karmabunny\kb\Arrays;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\PdbCondition;
use PDOStatement;
use PDO;
use ReflectionClass;

/**
 *
 * Query elements:
 * - `select(...$fields)`
 * - `andSelect(...$fields)`
 * - `from($table)`
 * - `leftJoin($table, $conditions, $combine)`
 * - `innerJoin($table, $conditions, $combine)`
 * - `where($conditions, $combine)`
 * - `andWhere($conditions, $combine)`
 * - `orWhere($conditions, $combine)`
 * - `groupBy($field)`
 * - `orderBy(...$fields)`
 * - `limit($limit)`
 * - `offset($limit)`
 *
 * Shorthands:
 * - `find($table, $conditions)`
 *
 * Terminator methods:
 * - `build(): string`
 * - `value($field): string`
 * - `one($class): array|object`
 * - `all($limit): array`
 * - `map($key, $value): array`
 * - `keyed($key): array`
 * - `column(): array`
 * - `count(): int`
 * - `pdo(): PDO`
 *
 * Class builders:
 * - `one(): object`
 * - `all(): object[]`
 * - `keyed(): [key => object]`
 *
 * @package karmabunny\pdb
 */
class PdbQuery
{
    /** @var Pdb|null */
    protected $pdb;

    /** @var array list [type, conditions, combine] */
    private $_conditions = [];

    /** @var array list [field, alias] */
    private $_select = [];

    /** @var array single [field, alias] */
    private $_from = [];

    /** @var array list [type, [table, alias], conditions, combine] */
    private $_joins = [];

    /** @var array list [field, order] */
    private $_order = [];

    /** @var string[] */
    private $_group = [];

    private $_limit = 0;

    private $_offset = 0;

    private $_as = null;


    /**
     *
     * @param Pdb|PdbConfig|array $pdb
     */
    public function __construct($pdb)
    {
        if ($pdb instanceof Pdb) {
            $this->pdb = $pdb;
        }
        else {
            $this->pdb = Pdb::create($pdb);
        }
    }


    /**
     * Select a list of fields.
     *
     * Note, this will replace any previous select().
     *
     * @param string|string[] $fields field => alias
     * @return static
     * @throws InvalidArgumentException
     */
    public function select(...$fields)
    {
        $this->_select = [];
        $this->andSelect(...$fields);
        return $this;
    }


    /**
     * Select more things.
     *
     * This does _not_ replace previous select(), only adds.
     *
     * @param string ...$fields
     * @return static
     * @throws InvalidArgumentException
     */
    public function andSelect(...$fields)
    {
        $fields = Arrays::flatten($fields, true);

        foreach ($fields as $key => $value) {
            if (is_numeric($key)) {
                $this->_select[] = Pdb::validateAlias($value, true);
            }
            else {
                Pdb::validateIdentifierExtended($key, true);
                $this->_select[] = [$key, $value];
            }
        }

        return $this;
    }


    /**
     *
     * @param string|string[] $table
     * @return static
     * @throws InvalidArgumentException
     */
    public function from($table)
    {
        $this->_from = Pdb::validateAlias($table);
        return $this;
    }


    /**
     *
     * @param string $type
     * @param string|string[] $table
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine
     * @return static
     * @throws InvalidArgumentException
     */
    private function _join(string $type, $table, array $conditions, string $combine = 'AND')
    {
        $table = Pdb::validateAlias($table);
        $this->_joins[] = [$type, $table, $conditions, $combine];
        return $this;
    }


    /**
     *
     * @param string|string[] $table
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine
     * @return static
     */
    public function join($table, array $conditions, string $combine = 'AND')
    {
        return $this->innerJoin($table, $conditions, $combine);
    }


    /**
     *
     * @param string|string[] $table
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine
     * @return static
     */
    public function leftJoin($table, array $conditions, string $combine = 'AND')
    {
        $this->_join('LEFT', $table, $conditions, $combine);
        return $this;
    }


    /**
     *
     * @param string|string[] $table
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine
     * @return static
     */
    public function innerJoin($table, array $conditions, string $combine = 'AND')
    {
        $this->_join('INNER', $table, $conditions, $combine);
        return $this;
    }


    /**
     *
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine
     * @return static
     */
    public function where(array $conditions, $combine = 'AND')
    {
        $this->_conditions = [];
        if (!empty($conditions)) {
            $this->_conditions[] = ['WHERE', $conditions, $combine];
        }
        return $this;
    }


    /**
     *
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine AND | OR
     * @return static
     */
    public function andWhere(array $conditions, $combine = 'AND')
    {
        if (!empty($conditions)) {
            $this->_conditions[] = ['AND', $conditions, $combine];
        }
        return $this;
    }


    /**
     *
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine AND | OR
     * @return static
     */
    public function orWhere(array $conditions, $combine = 'OR')
    {
        if (!empty($conditions)) {
            $this->_conditions[] = ['OR', $conditions, $combine];
        }
        return $this;
    }


    /**
     *
     * @param string|string[] $fields
     * @return static
     */
    public function groupBy(...$fields)
    {
        $fields = Arrays::flatten($fields);
        $this->_group = $fields;
        return $this;
    }


    /**
     *
     * @param string|string[] $fields
     * @return static
     */
    public function orderBy(...$fields)
    {
        $fields = Arrays::flatten($fields, true);
        $fields = Arrays::normalizeOptions($fields, 'ASC');

        foreach ($fields as $field => $order) {
            Pdb::validateIdentifierExtended($field);
            Pdb::validateDirection($order);
            $this->_order[$field] = $order;
        }

        return $this;
    }


    /**
     *
     * @param string|string[] $fields
     * @return static
     */
    public function order(...$fields)
    {
        return $this->orderBy(...$fields);
    }


    /**
     *
     * @param string|string[] $fields
     * @return static
     */
    public function group(...$fields)
    {
        return $this->groupBy(...$fields);
    }


    /**
     *
     * @param int $limit
     * @return static
     */
    public function limit(int $limit)
    {
        $this->_limit = $limit;
        return $this;
    }


    /**
     *
     * @param int $offset
     * @return static
     */
    public function offset(int $offset)
    {
        $this->_offset = $offset;
        return $this;
    }


    /**
     *
     * @param string $table
     * @param array $conditions
     * @return static
     */
    public function find(string $table, $conditions = [])
    {
        $this->from($table);
        $this->where($conditions);
        return $this;
    }


    /**
     *
     * @return array [ sql, params ]
     * @throws InvalidArgumentException
     */
    public function build(): array
    {
        $sql = '';
        $params = [];

        // Build 'select'.
        if ($this->_select) {
            $fields = [];

            foreach ($this->_select as [$field, $alias]) {
                $field = $this->pdb->quoteField($field);

                if ($alias) {
                    $field .= ' AS ';
                    $field .= $this->pdb->quoteField($alias);
                }

                $fields[] = $field;
            }

            $sql .= 'SELECT ';
            $sql .= implode(', ', $fields);
            $sql .= ' ';
        }
        // No select? Build a wildcard.
        else {
            [$from, $alias] = $this->_from;
            if ($from) {
                $sql .= "SELECT ~{$from}.* ";
            }
            else {
                $sql .= "SELECT * ";
            }
        }

        // Build 'from'.
        if ($this->_from) {
            [$from, $alias] = $this->_from;

            $sql .= "FROM ~{$from} ";
            if ($alias) {
                $sql .= 'AS ';
                $sql .= $this->pdb->quoteField($alias);
                $sql .= ' ';
            }
        }

        // Build joiners.
        foreach ($this->_joins as [$type, $table, $conditions, $combine]) {
            [$table, $alias] = $table;

            $sql .= "{$type} JOIN ~{$table} ";
            if ($alias) {
                $sql .= 'AS ';
                $sql .= $this->pdb->quoteField($alias);
                $sql .= ' ';
            }

            $sql .= 'ON ' . $this->pdb->buildClause($conditions, $params, $combine);
            $sql .= ' ';
        }

        // Build where clauses.
        $first = true;
        foreach ($this->_conditions as [$type, $conditions, $combine]) {
            if (!$first) {
                $type = 'WHERE';
                $first = false;
            }

            $sql .= $type . ' ';
            $sql .= $this->pdb->buildClause($conditions, $params, $combine);
            $sql .= ' ';
        }

        if ($this->_group) {
            $fields = [];
            foreach ($this->_group as $field) {
                $fields[] = $this->pdb->quoteField($field);
            }

            $sql .= 'GROUP BY ';
            $sql .= implode(', ', $fields);
            $sql .= ' ';
        }

        if ($this->_order) {
            $fields = [];
            foreach ($this->_order as $field => $order) {
                $field = $this->pdb->quoteField($field);
                $fields[] = "{$field} {$order}";
            }

            $sql .= 'ORDER BY ';
            $sql .= implode(', ', $fields);
            $sql .= ' ';

        }

        if ($this->_limit) {
            $params[] = $this->_limit;
            $sql .= 'LIMIT ? ';
        }

        if ($this->_offset) {
            $params[] = $this->_offset;
            $sql .= 'OFFSET ? ';
        }

        return [trim($sql), $params];
    }


    /**
     *
     * @param string|null $field
     * @return string
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function value(string $field = null): string
    {
        if ($field) {
            $this->select($field);
        }

        [$sql, $params] = $this->build();
        return $this->pdb->query($sql, $params, 'val');
    }


    /**
     *
     * @param string $class
     * @return static
     */
    public function as(string $class)
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("as({$class}) class does not exist");
        }

        $reflect = new ReflectionClass($class);
        if (!$reflect->isInstantiable()) {
            throw new InvalidArgumentException("as({$class}) is not a concrete class");
        }

        $this->_as = $class;
        return $this;
    }


    /**
     *
     * @param string $class
     * @return array|object
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function one(string $class = null)
    {
        $query = clone $this;

        $query->limit(1);
        if ($class) {
            $query->as($class);
        }

        [$sql, $params] = $query->build();
        $item = $this->pdb->query($sql, $params, 'row');

        if ($query->_as) {
            $class = $query->_as;
            $item = new $class($item);
        }

        return $item;
    }


    /**
     *
     * @param int|null $limit
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function all(int $limit = null): array
    {
        $query = clone $this;

        if ($limit) {
            $query->limit($limit);
        }

        [$sql, $params] = $query->build();
        $pdo = $this->pdb->query($sql, $params, 'pdo');

        if ($query->_as) {
            $class = $query->_as;

            $items = [];
            while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                $items[] = new $class($row);
            }
        }
        else {
            $items = $pdo->fetchAll(PDO::FETCH_ASSOC);
        }

        $pdo->closeCursor();
        return $items;
    }


    /**
     *
     * @return Generator
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function iterator(): Generator
    {
        $query = clone $this;

        [$sql, $params] = $query->build();
        $pdo = $this->pdb->query($sql, $params, 'pdo');

        if ($query->_as) {
            $class = $query->_as;

            while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                yield new $class($row);
            }
        }
        else {
            while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }
        }

        $pdo->closeCursor();
    }


    /**
     *
     * @param string|null $key
     * @param string|null $value
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function map(string $key = null, string $value = null): array
    {
        $query = clone $this;

        // Replace select with key->value.
        if ($key and $value) {
            $query->select($key, $value);
        }
        // ak. no.
        else if ($key or $value) {
            throw new InvalidArgumentException('map() accepts exactly 2 arguments or none.');
        }

        [$sql, $params] = $query->build();
        return $this->pdb->query($sql, $params, 'map');
    }


    /**
     *
     * This ends a query.
     *
     * @param string|null $key
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function keyed(string $key = null): array
    {
        $query = clone $this;

        // Explicitly defined key.
        if (!$key) $key = 'id';

        $query->andSelect($key);

        [$sql, $params] = $query->build();
        $pdo = $this->pdb->query($sql, $params, 'pdo');

        // Convert into objects.
        if ($query->_as) {
            $class = $query->_as;
        }

        $map = [];
        while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
            $id = $row[$key];

            if (isset($class)) {
                $row = new $class($row);
            }

            $map[$id] = $row;
        }

        $pdo->closeCursor();
        return $map;
    }


    /**
     *
     * @param string|null $field
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function column(string $field = null): array
    {
        $query = clone $this;

        // Insert field if missing.
        if ($field) {
            $query->select($field);
        }

        [$sql, $params] = $query->build();
        return $this->pdb->query($sql, $params, 'col');
    }


    /**
     *
     * @param string|null $table
     * @param array $conditions
     * @return int
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function count(string $table = null, array $conditions = []): int
    {
        $query = clone $this;

        // Counts never need a complex select.
        $query->select('1');

        if ($table) {
            $query->from($table);
        }
        if ($conditions) {
            $query->where($conditions);
        }

        [$sql, $params] = $query->build();
        return $this->pdb->query($sql, $params, 'count');
    }


    /**
     *
     * @return PDOStatement
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function pdo(): PDOStatement
    {
        $query = clone $this;
        [$sql, $params] = $query->build();
        return $this->pdb->query($sql, $params, 'pdo');
    }

}
