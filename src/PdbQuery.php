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
        $this->_table = $table;
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
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
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
            $fields = self::aliasAll($this->_select);
            $fields = implode(',', $fields);
            $sql .= 'SELECT ' . $fields;

            if ($this->_and_select and strpos($fields, '*') === 0) {
                $fields = self::aliasAll($this->_and_select);
                $fields = implode(',', $fields);
                $sql .= ',' . $fields;
            }

            $sql .= ' ';
        }
        else if (strtolower(substr(trim($sql), 0, 6)) === 'select') {
            [$from, $alias] = self::alias($this->_from);
            if ($from) {
                $sql .= "SELECT ~{$from}.* ";
            }
            else {
                $sql .= "SELECT * ";
            }
        }

        $this->injectRaw($sql, 'select');

        if ($this->_from) {
            [$from, $alias] = self::alias($this->_from);

            $sql .= "FROM ~{$from} ";
            if ($alias) $sql .= "AS {$alias} ";

            $this->injectRaw($sql, 'from');
        }

        // Build joiners.
        foreach ($this->_joins as $type => $join) {
            foreach ($join as $table => [$conditions, $combine]) {
                [$table, $alias] = self::alias($table);

                $sql .= "{$type} JOIN ~{$table} ";
                if ($alias) $sql .= "AS {$alias} ";
                $sql .= 'ON ' . Pdb::buildClause($conditions, $combine);
                $sql .= ' ';
            }
        }

        // Build where clauses.
        foreach ($this->_conditions as $type => [$conditions, $combine]) {
            $sql .= $type . ' ';
            $sql .= Pdb::buildClause($conditions, $params, $combine);
            $sql .= ' ';
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
     * @return array
     * @throws InvalidArgumentException
     */
    public function one(): array
    {
        $this->limit(1);

        [$sql, $params] = $this->build();
        return $this->pdo->query($sql, $params, 'row');
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
        return $this->pdb->query($sql, $params, 'arr');
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
            return $this->pdb->query($sql, $params, 'map-arr');
        }
    }


    /**
     *
     * @param string|null $field
     * @return array
     * @throws InvalidArgumentException
     */
    public function column(string $field = null): array
    {
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
        [$sql, $params] = $this->build();
        return $this->pdb->query($sql, $params, 'pdo');
    }


    /**
     *
     * @param string $value
     * @return array [field, alias]
     */
    public static function alias(string $value): array
    {
        $match = [];
        if (!preg_match('/([^\s]+)\s+(?:AS\s+)?([^\s]+)/i', $value, $match)) {
            return [ trim($value), null ];
        }

        return [ trim($match[1]), trim($match[2]) ];
    }


    /**
     *
     * @param string[] $fields
     * @return string[]
     */
    public static function aliasAll(array $fields): array
    {
        foreach ($fields as &$field) {
            [$field, $alias] = self::alias($field);
            if ($alias) $field .= " AS {$alias}";
        }
        return $fields;
    }


    /**
     *
     * @param string $sql
     * @param string $type
     * @return void
     */
    private function injectRaw(string &$sql, string $type)
    {
        if ($this->_sql[$type]) {
            $sql .= trim($this->_sql[$type]) . ' ';
        }
    }
}
