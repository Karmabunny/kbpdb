<?php

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
use PDO;
use Traversable;

/**
 *
 * @package karmabunny\pdb
 */
class MysqliStatement extends PdbStatement
{

    const FETCH = [
        PDO::FETCH_ASSOC => MYSQLI_ASSOC,
        PDO::FETCH_NUM => MYSQLI_NUM,
        PDO::FETCH_BOTH => MYSQLI_BOTH,
    ];

    /** @var mysqli */
    public $db;

    /** @var mysqli_stmt */
    public $stmt;

    /** @var array */
    protected $params = [];

    /** @var mysqli_result|null */
    protected $cursor;


    /**
     *
     * @param mysqli $db
     * @param mysqli_stmt $stmt
     * @param string $query
     */
    public function __construct(mysqli $db, mysqli_stmt $stmt, string $query)
    {
        $this->db = $db;
        $this->stmt = $stmt;
        $this->queryString = $query;
    }


    /** @inheritdoc */
    public function __destruct()
    {
        $this->closeCursor();
    }


    /**
     * Convert PDO fetch mode to mysqli fetch mode.
     *
     * @param int $mode `PDO::FETCH_` enum
     * @return int `MYSQLI_` enum
     * @throws InvalidArgumentException
     */
    public static function convertMode(int $mode): int
    {
        $mode = self::FETCH[$mode] ?? null;

        if ($mode === null) {
            throw new InvalidArgumentException('Unsupported/invalid fetch mode');
        }

        return $mode;
    }


    /**
     * Get the mysqli result object.
     *
     * This is a sort of cursor I guess? I don't know.
     *
     * @return mysqli_result
     * @throws mysqli_sql_exception
     */
    public function openCursor(): mysqli_result
    {
        if ($this->cursor) {
            return $this->cursor;
        }

        $this->cursor = $this->stmt->get_result();

        if ($this->cursor === false) {
            throw new mysqli_sql_exception($this->db->error);
        }

        return $this->cursor;
    }


    /**
     * Create an args array for mysqli_stmt::bind_param.
     *
     * The params array is a pair of `value, PDO type`.
     *
     * Return is something like: `[ 'issi', int, string, string, int ]`
     *
     * @param array $params [ value, type ]
     * @return array [ types, ...values ]
     */
    protected function createBinds(array $params): array
    {
        $binds = array_values($params);
        $types = '';

        foreach ($binds as &$bind) {
            [$value, $type] = $bind;

            switch ($type) {
                case PDO::PARAM_BOOL:
                case PDO::PARAM_INT:
                    $types .= 'i';
                    break;

                case PDO::PARAM_STR:
                default:
                    $types .= 's';
                    break;
            }

            $bind = $value;
        }
        unset($bind);

        array_unshift($binds, $types);
        return $binds;
    }


    /** @inheritdoc */
    public function bindValue($param, $var, int $type = PDO::PARAM_STR): bool
    {
       $this->params[$param] = [$var, $type];
       return true;
    }


    /** @inheritdoc */
    public function execute(array $params = []): bool
    {
        foreach ($params as &$param) {
            $params = [$param, PDO::PARAM_STR];
        }
        unset($param);

        $params = array_merge($this->params, $params);
        $binds = self::createBinds($params);

        if (count($binds) > 1) {
            call_user_func_array([$this->stmt, 'bind_param'], $binds);
        }

        return $this->stmt->execute();
    }


    /** @inheritdoc */
    public function fetch(int $mode = PDO::FETCH_BOTH)
    {
        $mode = self::convertMode($mode);
        $result = $this->openCursor();
        return $result->fetch_array($mode);
    }


    /** @inheritdoc */
    public function fetchAll(int $mode = PDO::FETCH_BOTH): array
    {
        $mode = self::convertMode($mode);
        $result = $this->openCursor();

        $arr = [];
        while ($row = $result->fetch_array($mode)) {
            $arr[] = $row;
        }

        return $arr;
    }


    /** @inheritdoc */
    public function columnCount(): int
    {
        return $this->stmt->field_count;
    }


    /** @inheritdoc */
    public function rowCount(): int
    {
        return $this->stmt->num_rows;
    }


    /** @inheritdoc */
    public function getIterator(): Traversable
    {
        $mode = self::convertMode($this->fetchMode);
        $result = $this->openCursor();

        for (;;) {
            $row = $result->fetch_array($mode);

            if ($row === null) {
                break;
            }

            yield $row;
        }
    }


    /** @inheritdoc */
    public function closeCursor()
    {
        if ($this->cursor) {
            $this->cursor->free_result();
            $this->cursor = null;
        }

        // Consume all the things.
        if ($this->stmt->more_results()) {
            while ($this->stmt->next_result()) {
                $this->stmt->store_result();
            }
        }
    }

}
