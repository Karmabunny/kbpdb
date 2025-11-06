<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use ArrayIterator;
use Generator;
use InvalidArgumentException;
use JsonSerializable;
use karmabunny\kb\Arrayable;
use karmabunny\kb\Arrays;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\InvalidConditionException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\PdbCondition;
use karmabunny\pdb\Models\PdbConditionInterface;
use karmabunny\pdb\Models\PdbRawCondition;
use karmabunny\pdb\Models\PdbReturn;
use PDOStatement;
use PDO;
use ReflectionClass;
use ReturnTypeWillChange;

/**
 *
 * Query elements:
 * - `select(...$fields)`
 * - `andSelect(...$fields)`
 * - `from($table, [$alias])`
 * - `alias($alias)`
 * - `leftJoin($table, $conditions, $combine)`
 * - `innerJoin($table, $conditions, $combine)`
 * - `where($conditions, $combine)`
 * - `andWhere($conditions, $combine)`
 * - `orWhere($conditions, $combine)`
 * - `with($subQuery, $alias)`
 * - `union($subQuery)`
 * - `groupBy($field)`
 * - `orderBy(...$fields)`
 * - `limit($limit)`
 * - `offset($limit)`
 *
 * Shorthands:
 * - `find($table, $conditions)`
 *
 * Modifiers:
 * - `as($class)`
 * - `throw($bool)`
 * - `cache($ttl)`
 * - `indexBy($field)`
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
 * - `each($size): iterable<object>`
 *
 * @package karmabunny\pdb
 */
class PdbQuery implements PdbQueryInterface, Arrayable, JsonSerializable
{

    /** @var Pdb */
    protected $pdb;

    /**
     * - true: use `pdb.config.ttl`
     * - false: no cache (default)
     * - int: cache for this many seconds
     *
     * @var int|bool
     */
    protected $_cache_ttl = false;

    /**
     * Override the cache key.
     *
     * @var string|null
     */
    protected $_cache_key = null;

    /** @var array list [type, conditions, combine] */
    protected $_where = [];

    /** @var array list [field, alias] */
    protected $_select = [];

    /**
     * single [field, alias]
     * @var array{0:string|PdbQueryInterface,1:string|null}|array{}
    */
    protected $_from = [];

    /**
     * list [ type, [table, alias], conditions, combine ]
     * @var array{0:string,1:array,2:array,3:string}[]
     */
    protected $_joins = [];

    /**
     * list [type, conditions, combine]
     * @var array{0:string,1:array,2:string}[]
     */
    protected $_having = [];

    /**
     * list [query, alias]
     * @var array{0:PdbQueryInterface,1:string}[]
     */
    protected $_with = [];

    /** @var PdbQueryInterface[] */
    protected $_union = [];

    /** @var array list [field, order] */
    protected $_order = [];

    /** @var string[] */
    protected $_group = [];

    /** @var int */
    protected $_limit = 0;

    /** @var int */
    protected $_offset = 0;

    /** @var string|null */
    protected $_as = null;

    /** @var bool */
    protected $_throw = true;

    /** @var string|null */
    protected $_keyed = null;


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
        // @phpstan-ignore-next-line : not true.
        $iterate = new ArrayIterator($this);
        $array = [];

        foreach ($iterate as $key => $item) {
            if ($key === 'pdb') continue;

            $array[$key] = $item;
        }

        return $array;
    }


    /** @inheritdoc */
    #[ReturnTypeWillChange]
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
     * @param string|string[] $fields alias => column
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
     * @param string|string[] $fields alias => column
     * @return static
     * @throws InvalidArgumentException
     */
    public function andSelect(...$fields)
    {
        $fields = Arrays::flatten($fields, true, 2);

        foreach ($fields as $alias => $value) {
            if ($value instanceof PdbQueryInterface) {
                if (is_numeric($alias)) {
                    throw new InvalidArgumentException('Nested queries must have an alias');
                }

                Pdb::validateIdentifier($alias);
                $this->_select[] = [$value, $alias];
            }
            // Split strings by commas, provided there's no function in here.
            else if (
                is_numeric($alias)
                and is_string($value)
                and !preg_match('/[()]/', $value)
            ) {
                $subfields = preg_split('/\s*,\s*/', $value);

                foreach ($subfields as $field) {
                    $field = PdbHelpers::parseAlias($field);
                    Pdb::validateAlias($field, false);
                    $this->_select[] = $field;
                }
            }
            else {
                $field = is_numeric($alias) ? $value : [$value, $alias];
                $field = PdbHelpers::parseAlias($field);
                Pdb::validateAlias($field, true);
                $this->_select[] = $field;
            }
        }

        return $this;
    }


    /**
     * Set the FROM table and alias (optional).
     *
     * @param string|string[]|PdbQueryInterface $table
     * @param string $alias
     * @return static
     * @throws InvalidArgumentException
     */
    public function from($table, ?string $alias = null)
    {
        if (!$alias and is_array($table)) {
            [$table, $alias] = PdbHelpers::parseAlias($table);
        }

        if (!$alias and $table instanceof PdbQueryInterface) {
            throw new InvalidArgumentException('from() expects an alias when using a sub-query');
        }

        Pdb::validateAlias([$table, $alias]);
        $this->_from = [$table, $alias];
        return $this;
    }


    /**
     * Set the alias for the FROM table.
     *
     * This can only modify an existing FROM statement.
     *
     * @param string $alias
     * @return static
     * @throws InvalidArgumentException
     */
    public function alias(string $alias)
    {
        Pdb::validateIdentifier($alias);

        if (!empty($this->_from)) {
            $this->_from[1] = $alias;
        }

        return $this;
    }


    /**
     *
     * @param string $type
     * @param string|string[] $table
     * @param string|PdbConditionInterface|array $conditions
     * @param string $combine
     * @return static
     * @throws InvalidArgumentException
     */
    protected function _join(string $type, $table, $conditions, string $combine = 'AND')
    {
        [$table, $alias] = PdbHelpers::parseAlias($table);

        if ($table instanceof PdbQueryInterface) {
            if (!$alias) {
                throw new InvalidArgumentException('join() expects an alias when using a sub-query');
            }

            Pdb::validateIdentifier($table);
        }
        else {
            Pdb::validateAlias([$table, $alias]);
        }

        if (is_string($conditions)) {
            $conditions = [ new PdbRawCondition($conditions) ];
        }
        else if ($conditions instanceof PdbConditionInterface) {
            $conditions = [ $conditions ];
        }
        // Backward compat, shallow encode raw conditions.
        else {
            foreach ($conditions as &$item) {
                if (is_string($item)) {
                    $item = new PdbRawCondition($item);
                }
            }
            unset($item);
        }

        $this->_joins[] = [$type, [$table, $alias], $conditions, $combine];
        return $this;
    }


    /**
     *
     * @param string|string[] $table
     * @param string|PdbConditionInterface|array $conditions
     * @param string $combine
     * @return static
     */
    public function join($table, $conditions, string $combine = 'AND')
    {
        return $this->innerJoin($table, $conditions, $combine);
    }


    /**
     *
     * @param string|string[] $table
     * @param string|PdbConditionInterface|array $conditions
     * @param string $combine
     * @return static
     */
    public function leftJoin($table, $conditions, string $combine = 'AND')
    {
        $this->_join('LEFT', $table, $conditions, $combine);
        return $this;
    }


    /**
     *
     * @param string|string[] $table
     * @param string|PdbConditionInterface|array $conditions
     * @param string $combine
     * @return static
     */
    public function innerJoin($table, $conditions, string $combine = 'AND')
    {
        $this->_join('INNER', $table, $conditions, $combine);
        return $this;
    }


    /**
     *
     * @param array $conditions
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
     * @param array $conditions
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
     * @param array $conditions
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
     * @param array $conditions
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
     * Add a sub-query to the WITH clause.
     *
     * @param PdbQueryInterface $query
     * @param string $alias
     * @return static
     */
    public function with(PdbQueryInterface $query, string $alias)
    {
        Pdb::validateIdentifier($alias);
        $this->_with[] = [ $query, $alias ];
        return $this;
    }


    /**
     * Add a sub-query to the UNION clause.
     *
     * @param PdbQueryInterface $query
     * @return static
     */
    public function union(PdbQueryInterface $query)
    {
        $this->_union[] = $query;
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
        $fields = Arrays::flatten($fields, false, 2);

        foreach ($fields as $field) {
            $field = preg_split('/[, ]+/', $field);
            array_push($this->_group, ...$field);
        }

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
        $fields = Arrays::flatten($fields, true, 3);

        $this->_order = [];

        foreach ($fields as $field => $order) {

            // Convert strings to key->value pair.
            if (is_numeric($field) and is_string($order)) {
                $field = explode(' ', $order, 2);

                if (count($field) == 2) {
                    [$field, $order] = $field;
                }
                else {
                    $field = $field[0];
                    $order = 'ASC';
                }
            }

            // Convert PHP constants to strings.
            if ($order === SORT_ASC) {
                $order = 'ASC';
            }
            else if ($order === SORT_DESC) {
                $order = 'DESC';
            }

            Pdb::validateIdentifierExtended($field, true);
            Pdb::validateDirection($order);
            $this->_order[$field] = $order;
        }

        return $this;
    }


    /**
     *
     * @param string|string[] $fields
     * @return static
     * @deprecated use orderBy
     */
    public function order(...$fields)
    {
        return $this->orderBy(...$fields);
    }


    /**
     *
     * @param string|string[] $fields
     * @return static
     * @deprecated use groupBy
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
     * @param string|string[]|PdbQueryInterface $table
     * @param array $conditions
     * @return static
     */
    public function find($table, $conditions = [])
    {
        $this->from($table);
        $this->where($conditions);
        return $this;
    }


    /**
     *
     * @param string|null $class
     * @return static
     * @throws InvalidArgumentException
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
     * Emit RowMissingExceptions if the record is not found.
     *
     * This applies to:
     * - one()
     * - value()
     *
     * @param bool $throw
     * @return static
     */
    public function throw(bool $throw = true)
    {
        $this->_throw = $throw;
        return $this;
    }


    /**
     * Specify a key for array results.
     *
     * This applies to:
     * - all()
     * - column()
     * - iterator()
     *
     * @param string|null $field
     * @return static
     */
    public function indexBy($field)
    {
        $this->_keyed = $field;
        return $this;
    }


    /**
     *
     * @param string|null $key
     * @param int|bool $ttl seconds
     * @return static
     */
    public function cache(?string $key = null, $ttl = true)
    {
        $this->_cache_key = $key;

        // Enable cache, this uses the global TTL.
        if ($ttl === true) {
            $this->_cache_ttl = true;
        }
        // Custom TTL value.
        else if ($ttl) {
            $this->_cache_ttl = $ttl;
        }
        // Disable cache, false/null/0.
        else {
            $this->_cache_ttl = false;
        }

        return $this;
    }


    /**
     *
     * @return void
     * @throws InvalidConditionException
     */
    public function validate()
    {
        foreach ($this->_joins as $item) {
            PdbCondition::fromArray($item[2], true);
        }

        foreach ($this->_where as $item) {
            PdbCondition::fromArray($item[1], true);
        }

        foreach ($this->_having as $item) {
            PdbCondition::fromArray($item[1], true);
        }
    }


    /**
     *
     * @param bool $validate
     * @return array [ sql, params ]
     * @throws InvalidConditionException
     * @throws InvalidArgumentException
     */
    public function build(bool $validate = true): array
    {
        $query = clone $this;
        $this->_beforeBuild($query);
        [$sql, $params] = $query->_build($validate);
        $this->_afterBuild($sql, $params);
        return [$sql, $params];
    }


    /**
     *
     * @param PdbQuery $query
     * @return void
     */
    protected function _beforeBuild(PdbQuery &$query)
    {
    }


    /**
     *
     * @param string $sql
     * @param array $params
     * @return void
     */
    protected function _afterBuild(string &$sql, array &$params)
    {
        $ids = $this->getIdentifiers();

        $sql = $this->pdb->quoteIdentifiers($sql, array_keys($ids), false);
        $sql = $this->pdb->insertPrefixes($sql);
    }


    /**
     *
     * @param bool $validate
     * @return array [ sql, params ]
     * @throws InvalidConditionException
     * @throws InvalidArgumentException
     */
    protected function _build(bool $validate = true): array
    {
        $sql = '';
        $params = [];

        if ($this->_with) {
            foreach ($this->_with as [$query, $alias]) {
                [$subSql, $subParams] = $query->build($validate);
                $alias = $this->pdb->quoteField($alias);
                $sql .= "WITH {$alias} AS ({$subSql})\n";
                $params = array_merge($params, $subParams);
            }
        }

        // Build 'select'.
        if ($this->_select) {
            $fields = [];

            foreach ($this->_select as $item) {
                [$field, $alias] = $item + [null, null];

                if ($field instanceof PdbQueryInterface) {
                    [$field, $_params] = $field->build($validate);
                    $params = array_merge($params, $_params);
                }
                else if (!preg_match(PdbHelpers::RE_FUNCTION, $field)) {
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
            [$from, $alias] = $this->_from + [null, null];

            // Prefer the first alias, then use the table name.
            // Fallback to just a wildcard and cross your fingers.
            if ($alias) {
                $alias = $this->pdb->quoteField($alias);
                $sql .= "SELECT {$alias}.* ";
            }
            else if (is_string($from)) {
                $sql .= "SELECT ~{$from}.* ";
            }
            else {
                $sql .= "SELECT * ";
            }
        }

        // Build 'from'.
        if ($this->_from) {
            [$from, $alias] = $this->_from + [null, null];

            if ($from instanceof PdbQueryInterface) {
                // This is also performed in the from() method.
                if ($validate and empty($alias)) {
                    throw new InvalidArgumentException('from() expects an alias when using a sub-query');
                }

                [$subSql, $subParams] = $from->build($validate);
                $alias = $alias ?: ('subQuery' . sha1($subSql));

                $sql .= "FROM ({$subSql}) ";
                $sql .= 'AS ';
                $sql .= $this->pdb->quoteField($alias);
                $sql .= ' ';

                $params = array_merge($params, $subParams);
            }
            else {
                $sql .= "FROM ~{$from} ";
                if ($alias) {
                    $sql .= 'AS ';
                    $sql .= $this->pdb->quoteField($alias);
                    $sql .= ' ';
                }
            }
        }

        // Build joiners.
        foreach ($this->_joins as [$type, $table, $conditions, $combine]) {
            [$table, $alias] = $table + [null, null];

            if ($table instanceof PdbQueryInterface) {
                // This is also performed in the join() method.
                if ($validate and empty($alias)) {
                    throw new InvalidArgumentException('from() expects an alias when using a sub-query');
                }

                [$subSql, $subParams] = $table->build($validate);
                $alias = $alias ?: ('subQuery' . sha1($subSql));

                $sql .= "{$type} JOIN ({$subSql}) ";
                $sql .= 'AS ';
                $sql .= $this->pdb->quoteField($alias);
                $sql .= ' ';
            }
            else {
                // This is actually an alias.
                if ($this->_with and in_array($table, array_column($this->_with, 1, 1))) {
                    $table = $this->pdb->quoteField($table);
                    $sql .= "{$type} JOIN {$table} ";
                }
                else {
                    $sql .= "{$type} JOIN ~{$table} ";
                }

                if ($alias) {
                    $sql .= 'AS ';
                    $sql .= $this->pdb->quoteField($alias);
                    $sql .= ' ';
                }
            }

            $sql .= 'ON ';
            $sql .= PdbCondition::buildClause($this->pdb, $conditions, $params, $combine, $validate);
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
            $sql .= PdbCondition::buildClause($this->pdb, $conditions, $params, $combine, $validate);
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
            $sql .= PdbCondition::buildClause($this->pdb, $conditions, $params, $combine, $validate);
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

        // Unions
        foreach ($this->_union as $query) {
            [$subSql, $subParams] = $query->build($validate);
            $sql = rtrim($sql) . "\nUNION\n{$subSql}";
            $params = array_merge($params, $subParams);
        }

        return [trim($sql), $params];
    }


    /**
     *
     * @param string $return_type
     * @return PdbReturnInterface
     * @throws InvalidArgumentException
     */
    public function getReturnConfig(string $return_type): PdbReturnInterface
    {
        return PdbReturn::parse([
            'type' => $return_type,
            'class' => $this->_as,
            'cache_ttl' => $this->_cache_ttl,
            'cache_key' => $this->_cache_key,
        ]);
    }


    /**
     *
     * @return (string|PdbQueryInterface)[] [ alias => table ]
     */
    public function getIdentifiers(): array
    {
        $ids = [];

        [$table, $alias] = $this->_from;

        if (is_string($table)) {
            $table = $this->pdb->getPrefix($table) . $table;
            $alias = $alias ?: $table;
        }

        if ($alias) {
            $ids[$alias] = $table;
        }

        foreach ($this->_joins as $join) {
            [$table, $alias] = $join[1];
            $table = $this->pdb->getPrefix($table) . $table;
            $alias = $alias ?: $table;
            $ids[$alias] = $table;
        }

        foreach ($this->_with as [$query, $alias]) {
            $ids[$alias] = $query;
        }

        return $ids;
    }


    /**
     *
     * @param string|null $field
     * @param bool|null $throw
     * @return string|null
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function value(?string $field = null, ?bool $throw = null): ?string
    {
        $query = clone $this;

        if ($throw !== null) {
            $query->_throw = $throw;
        }

        if ($field) {
            $query->select($field);
        }

        $type = $query->_throw ? 'val' : 'val?';
        return $query->execute($type);
    }


    /**
     *
     * @param bool|null $throw
     * @return array|object|null
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function one(?bool $throw = null)
    {
        $query = clone $this;

        if ($throw !== null) {
            $query->_throw = $throw;
        }

        $query->limit(1);

        $type = $query->_throw ? 'row' : 'row?';
        return $query->execute($type);
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

        if ($query->_keyed) {
            return $query->execute('map-arr:' . $query->_keyed);
        }
        else {
            return $query->execute('arr');
        }
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
        $pdo = $query->execute('pdo');

        if ($query->_as) {
            $class = $query->_as;

            if ($query->_keyed) {
                while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                    $key = $row[$query->_keyed] ?? reset($row);
                    yield $key => new $class($row);
                }
            }
            else {
                while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                    yield new $class($row);
                }
            }
        }
        else {
            if ($query->_keyed) {
                while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                    $key = $row[$query->_keyed] ?? reset($row);
                    yield $key => $row;
                }
            }
            else {
                while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                    yield $row;
                }
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
     * @param int $size
     * @return Generator
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function each(int $size): Generator
    {
        foreach ($this->batch($size) as $results) {
            foreach ($results as $result) {
                yield $result;
            }
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
        return $query->execute('map-arr:' . $key);
    }


    /**
     *
     * @param string|null $field
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function column(?string $field = null): array
    {
        $query = clone $this;

        // Insert field if missing.
        if ($field) {
            if ($query->_keyed) {
                $query->select($query->_keyed, $field);
            }
            else {
                $query->select($field);
            }
        }

        if ($query->_keyed) {
            return $query->execute('map');
        }
        else {
            return $query->execute('col');
        }
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
        if (!$query->_select) {
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
        $config = $this->getReturnConfig($return_type);
        return $this->pdb->query($sql, $params, $config);
    }
}
