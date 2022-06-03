<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use Generator;
use InvalidArgumentException;
use JsonSerializable;
use karmabunny\kb\Arrayable;
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
 * - `build(): [string, array]`
 * - `value($field): string`
 * - `one($throw): array|object`
 * - `all(): array`
 * - `map($key, $value): array`
 * - `keyed($key): array`
 * - `column(): array`
 * - `count(): int`
 * - `exists(): bool`
 * - `iterator(): iterable`
 * - `batch($size): iterable<array>`
 * - `pdo(): PDO`
 * - `execute(): mixed`
 *
 * Class builders:
 * - `one(): object`
 * - `all(): object[]`
 * - `keyed(): [key => object]`
 * - `iterator(): iterable<object>`
 * - `batch($size): iterable<object[]>`
 *
 * @package karmabunny\pdb
 */
class PdbQuery implements Arrayable, JsonSerializable
{

    /** @var Pdb */
    protected $pdb;

    /** @var array list [type, conditions, combine] */
    private $_where = [];

    /** @var array list [field, alias] */
    private $_select = [];

    /** @var array single [field, alias] */
    private $_from = [];

    /** @var array list [type, [table, alias], conditions, combine] */
    private $_joins = [];

    /** @var array list [type, conditions, combine] */
    private $_having = [];

    /** @var array list [field, order] */
    private $_order = [];

    /** @var string[] */
    private $_group = [];

    /** @var int */
    private $_limit = 0;

    /** @var int */
    private $_offset = 0;

    /** @var string|null */
    private $_as = null;


    /**
     *
     * @param Pdb|PdbConfig|array $pdb
     * @param array $config
     */
    public function __construct($pdb, array $config = [])
    {
        if ($pdb instanceof Pdb) {
            $this->pdb = $pdb;
        }
        else {
            $this->pdb = Pdb::create($pdb);
        }

        $this->update($config);
    }


    /**
     *
     * @param array $config
     * @return void
     */
    public function update(array $config)
    {
        foreach ($config as $key => $item) {
            if ($key === 'pdb') continue;
            $this->$key = $item;
        }
    }


    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this as $key => $item) {
            if ($key === 'pdb') continue;

            $array[$key] = $item;
        }

        return $array;
    }


    /** @inheritdoc */
    public function jsonSerialize()
    {
        return $this->toArray();
    }


    /**
     * Get a copy of this query.
     *
     * @return static
     */
    public function clone()
    {
        return clone $this;
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
    protected function _join(string $type, $table, array $conditions, string $combine = 'AND')
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
        $this->_where = [];
        if (!empty($conditions)) {
            $this->_where[] = ['WHERE', $conditions, $combine];
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
            $this->_where[] = ['AND', $conditions, $combine];
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
            $this->_where[] = ['OR', $conditions, $combine];
        }
        return $this;
    }


    /**
     *
     * @param (array|string|PdbCondition)[] $conditions
     * @param string $combine
     * @return static
     */
    public function having(array $conditions, $combine = 'AND')
    {
        $this->_having = [];
        if (!empty($conditions)) {
            $this->_having[] = ['HAVING', $conditions, $combine];
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
        $fields = array_filter($fields);
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
        $fields = array_filter($fields);
        $fields = Arrays::flatten($fields, true);
        $fields = Arrays::normalizeOptions($fields, 'ASC');

        $this->_order = [];

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

                if (!preg_match(PdbHelpers::RE_FUNCTION, $field)) {
                    $field = $this->pdb->quoteField($field);
                }

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
        foreach ($this->_where as [$type, $conditions, $combine]) {
            if ($first) {
                $type = 'WHERE';
                $first = false;
            }

            $sql .= $type . ' ';
            if ($type !== 'WHERE') $sql .= '(';
            $sql .= $this->pdb->buildClause($conditions, $params, $combine);
            if ($type !== 'WHERE') $sql .= ')';
            $sql .= ' ';
        }

        if ($this->_group) {
            $fields = [];
            foreach ($this->_group as $field) {
                if (!preg_match(PdbHelpers::RE_FUNCTION, $field)) {
                    $field = $this->pdb->quoteField($field);
                }

                $fields[] = $field;
            }

            $sql .= 'GROUP BY ';
            $sql .= implode(', ', $fields);
            $sql .= ' ';
        }

        // Build having clauses.
        $first = true;
        foreach ($this->_having as [$type, $conditions, $combine]) {
            if ($first) {
                $type = 'HAVING';
                $first = false;
            }

            $sql .= $type . ' ';
            $sql .= $this->pdb->buildClause($conditions, $params, $combine);
            $sql .= ' ';
        }

        // Build order by.
        if ($this->_order) {
            $fields = [];
            foreach ($this->_order as $field => $order) {
                if (!preg_match(PdbHelpers::RE_FUNCTION, $field)) {
                    $field = $this->pdb->quoteField($field);
                }

                $fields[] = "{$field} {$order}";
            }

            $sql .= 'ORDER BY ';
            $sql .= implode(', ', $fields);
            $sql .= ' ';

        }


        // Limit.
        if ($this->_limit) {
            $params[] = $this->_limit;
            $sql .= 'LIMIT ? ';
        }

        // Offset.
        if ($this->_offset) {
            $params[] = $this->_offset;
            $sql .= 'OFFSET ? ';
        }

        return [trim($sql), $params];
    }


    /**
     *
     * @param string|null $field
     * @param bool $throw
     * @return string|null
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function value(string $field = null, bool $throw = true): ?string
    {
        $query = clone $this;

        if ($field) {
            $query->select($field);
        }

        $type = $throw ? 'val' : 'val?';
        return $query->execute($type);
    }


    /**
     *
     * @param string|null $class
     * @return static
     */
    public function as(?string $class)
    {
        if (!$class) {
            $this->_as = null;
            return $this;
        }

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
     * @param bool $throw
     * @return array|object|null
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function one(bool $throw = true)
    {
        $query = clone $this;

        $query->limit(1);

        $type = $throw ? 'row' : 'row?';
        $item = $query->execute($type);

        if (!$throw and $item === null) {
            return null;
        }

        if ($query->_as) {
            $class = $query->_as;
            $item = new $class($item);
        }

        return $item;
    }


    /**
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function all(): array
    {
        $query = clone $this;
        $items = $query->execute('arr');

        if ($query->_as) {
            $class = $query->_as;

            foreach ($items as &$item) {
                $item = new $class($item);
            }
            unset($item);
        }

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
     * @param int $size
     * @return Generator<array>
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function batch(int $size): Generator
    {
        $query = clone $this;

        $cursor = 0;

        while (true) {
            $query->offset($cursor);
            $query->limit($size);

            $results = $query->all();

            if (empty($results)) {
                break;
            }

            yield $results;
            $cursor += $size;
        }
    }


    /**
     *
     * @param array|string|null $key
     * @param string|null $value
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function map($key = null, $value = null): array
    {
        $query = clone $this;

        // Replace select with key->value.
        if ($key) {
            // [ key => value ]
            if (is_array($key)) {
                $value = reset($key);
                $key = key($key);
                $query->select($key, $value);
            }
            // Separate arguments.
            else if ($value) {
                $query->select($key, $value);
            }
            // ak. no.
            else {
                throw new InvalidArgumentException('map() expects a [key => value] array, \'key, value\' arguments, or none.');
            }
        }

        return $query->execute('map');
    }


    /**
     *
     * This ends a query.
     *
     * @param string $key
     * @return array [ $key => item ]
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function keyed(string $key): array
    {
        $query = clone $this;
        $rows = $query->execute('arr');

        $map = [];

        // Convert into objects.
        if ($query->_as) {
            $class = $query->_as;

            while ($row = array_shift($rows)) {
                $id = $row[$key];
                $map[$id] = new $class($row);
            }
        }
        else {
            while ($row = array_shift($rows)) {
                $id = $row[$key];
                $map[$id] = $row;
            }
        }

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

        return $query->execute('col');
    }


    /**
     *
     * @return int
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function count(): int
    {
        $query = clone $this;

        // Use a fast search if no fields are given.
        if (!$this->_select) {
            $query->select('count(1)');
        }

        return $query->execute('count');
    }


    /**
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function exists(): bool
    {
        return $this->count() > 0;
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
        return $query->execute('pdo');
    }


    /**
     *
     * @param string $return_type
     * @return array|string|int|null|PDOStatement
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function execute(string $return_type)
    {
        [$sql, $params] = $this->build();
        return $this->pdb->query($sql, $params, $return_type);
    }
}
