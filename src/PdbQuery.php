<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;
use PDOStatement;
use PDO;

/**
 *
 * @package karmabunny\pdb
 */
class PdbQuery
{
    /** @var Pdo|null */
    protected $pdo;

    private $_conditions = [];

    private $_sql = [];

    private $_select = [];

    private $_and_select = [];

    private $_from = '';

    private $_joins = [];

    private $_order = [];

    private $_group = '';

    private $_limit = 0;

    private $_offset = 0;

    private $_last_cmd = '';

    private $_as = null;


    /**
     *
     * @param Pdb|PdbConfig|array $pdb
     * @return void
     */
    public function __construct($pdb)
    {
        if ($pdb instanceof Pdb) {
            $this->pdb = $pdb;
        }
        else {
            $this->pdb = new Pdb($pdb);
        }
    }


    /**
     *
     * @param string $sql
     * @return static
     */
    public function sql(string $sql)
    {
        $this->_sql[$this->_last_cmd] = $sql;
        return $this;
    }


    /**
     *
     * @param string[] $fields
     * @return static
     */
    public function select(...$fields)
    {
        $this->_select = $fields;
        $this->_last_cmd = 'select';
        return $this;
    }


    /**
     *
     * @param string[] $fields
     * @return static
     */
    public function andSelect(...$fields)
    {
        array_push($this->_and_select, $fields);
        return $this;
    }


    /**
     *
     * @param string $table
     * @return static
     */
    public function from(string $table)
    {
        $this->_from = $table;
        $this->_last_cmd = 'from';
        return $this;
    }


    /**
     *
     * @param string $type
     * @param string $table
     * @param array $conditions
     * @param string $combine
     * @return static
     */
    public function join(string $type, string $table, array $conditions, string $combine = 'AND')
    {
        $this->_joins[$type][$table] = [$conditions, $combine];
        $this->_last_cmd = 'join';
        return $this;
    }


    /**
     *
     * @param string $table
     * @param array $conditions
     * @param string $combine
     * @return static
     */
    public function leftJoin(string $table, array $conditions, string $combine = 'AND')
    {
        $this->join('left', $table, $conditions, $combine);
        return $this;
    }


    /**
     *
     * @param string $table
     * @param array $conditions
     * @param string $combine
     * @return static
     */
    public function innerJoin(string $table, array $conditions, string $combine = 'AND')
    {
        $this->join('inner', $table, $conditions, $combine);
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
        // print_r();
        // if (!is_array(reset($conditions))) {
        //     $conditions = [$conditions];
        // }
        $this->_conditions['WHERE'] = [[$conditions, $combine]];
        $this->_last_cmd = 'where';
        return $this;
    }


    /**
     *
     * @param array $condition
     * @param string $combine
     * @return static
     */
    public function andWhere(array $condition, $combine = 'AND')
    {
        $this->_conditions['AND'][] = [$condition, $combine];
        return $this;
    }


    /**
     *
     * @param array $condition
     * @param string $combine
     * @return static
     */
    public function orWhere(array $condition, $combine = 'OR')
    {
        $this->_conditions['OR'][] = [$condition, $combine];
        return $this;
    }


    /**
     *
     * @param string $field
     * @return static
     */
    public function groupBy(string $field)
    {
        $this->_group = $field;
        $this->_last_cmd = 'group';
        return $this;
    }


    /**
     *
     * @param string $field
     * @return static
     */
    public function orderBy(string $field)
    {
        $this->_order[] = $field;
        $this->_last_cmd = 'order';
        return $this;
    }


    /**
     *
     * @param int $limit
     * @return static
     */
    public function limit(int $limit)
    {
        $this->_limit = $limit;
        $this->_last_cmd = 'limit';
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
        $this->_last_cmd = 'offset';
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
        if (empty($this->_select)) {
            $this->select('*');
        }

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

        $this->injectRaw($sql, '');

        if ($this->_select) {
            $fields = PdbHelpers::normalizeAliases($this->_select);
            $fields = implode(',', $fields);
            $sql .= 'SELECT ' . $fields;

            if ($this->_and_select and strpos($fields, '*') === 0) {
                $fields = PdbHelpers::normalizeAliases($this->_and_select);
                $fields = implode(',', $fields);
                $sql .= ',' . $fields;
            }

            $sql .= ' ';
        }
        else if (strtolower(substr(trim($sql), 0, 6)) === 'select') {
            [$from, $alias] = PdbHelpers::alias($this->_from);
            if ($from) {
                $sql .= "SELECT ~{$from}.* ";
            }
            else {
                $sql .= "SELECT * ";
            }
        }

        $this->injectRaw($sql, 'select');

        if ($this->_from) {
            [$from, $alias] = PdbHelpers::alias($this->_from);

            $sql .= "FROM ~{$from} ";
            if ($alias) $sql .= "AS {$alias} ";

            $this->injectRaw($sql, 'from');
        }

        // Build joiners.
        foreach ($this->_joins as $type => $join) {
            foreach ($join as $table => [$conditions, $combine]) {
                [$table, $alias] = PdbHelpers::alias($table);

                $sql .= "{$type} JOIN ~{$table} ";
                if ($alias) $sql .= "AS {$alias} ";
                $sql .= 'ON ' . Pdb::buildClause($conditions, $combine);
                $sql .= ' ';
            }
        }

        // Build where clauses.
        foreach ($this->_conditions as $type => $clauses) {
            foreach ($clauses as [$conditions, $combine]) {
                $sql .= $type . ' ';
                $sql .= Pdb::buildClause($conditions, $params, $combine);
                $sql .= ' ';
            }
        }

        $this->injectRaw($sql, 'where');

        if ($this->_group) {
            $sql .= "GROUP BY {$this->_group} ";

            $this->injectRaw($sql, 'group');
        }

        if ($this->_order) {
            $sql .= "ORDER BY {$this->_order} ";

            $this->injectRaw($sql, 'order');
        }

        if ($this->_offset) {
            $sql .= "OFFSET {$this->_offset} ";

            $this->injectRaw($sql, 'offset');
        }

        if ($this->_limit) {
            $sql .= "LIMIT {$this->_limit} ";

            $this->injectRaw($sql, 'limit');
        }

        return [trim($sql), $params];
    }


    /**
     *
     * @param string|null $field
     * @return string
     * @throws InvalidArgumentException
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
        if (
            !is_subclass_of($class, PdbModelTrait::class) and
            !is_subclass_of($class, PdbModel::class)
        ) {
            throw new InvalidArgumentException("as({$class}) must be a PdbModel");
        }

        $this->_as = $class;
        return $this;
    }


    /**
     *
     * @param string $class
     * @return array|object
     * @throws InvalidArgumentException
     */
    public function one(string $class = null)
    {
        $this->limit(1);
        if ($class) {
            $this->as($class);
        }

        [$sql, $params] = $this->build();
        $item = $this->pdb->query($sql, $params, 'row');

        if ($this->_as) {
            $class = $this->_as;
            $item = new $class($item);
        }

        return $item;
    }


    /**
     *
     * @param int|null $limit
     * @return array
     * @throws InvalidArgumentException
     */
    public function all(int $limit = null): array
    {
        if ($limit) {
            $this->limit($limit);
        }

        [$sql, $params] = $this->build();
        $items = $this->pdb->query($sql, $params, 'arr');

        if ($this->_as) {
            $class = $this->_as;
            foreach ($items as &$item) {
                $item = new $class($item);
            }
        }

        return $items;
    }


    /**
     *
     * @param string|null $key
     * @param string|null $value
     * @return array
     * @throws InvalidArgumentException
     */
    public function map(string $key = null, string $value = null): array
    {
        // TODO Throw if non-empty 'as'.

        if ($key and $value) {
            $this->select($key, $value);
        }
        else if ($key or $value) {
            throw new InvalidArgumentException('map() accepts exactly 2 arguments or none.');
        }

        [$sql, $params] = $this->build();
        return $this->pdb->query($sql, $params, 'map');
    }


    /**
     *
     * @param string|null $key
     * @return array
     * @throws InvalidArgumentException
     */
    public function keyed(string $key = null): array
    {
        // Explicitly defined key.
        if ($key) {
            $this->andSelect($key);

            [$sql, $params] = $this->build();
            $pdo = $this->pdb->query($sql, $params, 'pdo');

            $map = [];
            while ($row = $pdo->fetch(PDO::FETCH_ASSOC)) {
                $id = $row[$key];
                $map[$id] = $row;
            }
        }
        // Use the first row.
        else {
            [$sql, $params] = $this->build();
            $map = $this->pdb->query($sql, $params, 'map-arr');
        }

        if ($this->_as) {
            $class = $this->_as;
            foreach ($map as &$item) {
                $item = new $class($item);
            }
        }

        return $map;
    }


    /**
     *
     * @param string|null $field
     * @return array
     * @throws InvalidArgumentException
     */
    public function column(string $field = null): array
    {
        // TODO Throw if non-empty 'as'.

        if ($field) {
            $this->select($field);
        }

        [$sql, $params] = $this->build();
        return $this->pdb->queryuery($sql, $params, 'col');
    }


    /**
     *
     * @param string|null $table
     * @param array $conditions
     * @return int
     */
    public function count(string $table = null, array $conditions = []): int
    {
        // TODO Throw if non-empty 'as'.

        $this->select('1');

        if ($table) {
            $this->from($table);
        }
        if ($conditions) {
            $this->where($conditions);
        }

        [$sql, $params] = $this->build();
        return $this->pdb->query($sql, $params, 'count');
    }


    /**
     *
     * @return PDOStatement
     * @throws InvalidArgumentException
     */
    public function pdo(): PDOStatement
    {
        // TODO Throw if non-empty 'as'.

        [$sql, $params] = $this->build();
        return $this->pdb->query($sql, $params, 'pdo');
    }


    /**
     *
     * @param string $sql
     * @param string $type
     * @return void
     */
    private function injectRaw(string &$sql, string $type)
    {
        if ($this->_sql[$type] ?? false) {
            $sql .= trim($this->_sql[$type]) . ' ';
        }
    }
}
