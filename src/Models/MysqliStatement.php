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

    /**
     * Conversion between PDO and Mysqli fetch modes.
     */
    const FETCH = [
        PDO::FETCH_ASSOC => MYSQLI_ASSOC,
        PDO::FETCH_NUM => MYSQLI_NUM,
        PDO::FETCH_BOTH => MYSQLI_BOTH,
    ];

    /** @var mysqli */
    public $db;

    /** @var mysqli_stmt */
    public $stmt;

    /** @var array Note, order of parameters are essential. */
    protected $named;

    /** @var array [ value, PDO type ] */
    protected $params = [];

    /** @var mysqli_result|null */
    protected $cursor;

    /**
     *
     * @param mysqli $db
     * @param mysqli_stmt $stmt
     * @param string $query
     * @param string[] $named
     */
    public function __construct(mysqli $db, mysqli_stmt $stmt, string $query, array $named = [])
    {
        $this->db = $db;
        $this->stmt = $stmt;
        $this->queryString = $query;
        $this->named = $named;
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
     * @param string $types output param, like `'issi'`
     * @return array values like `[ int, string, string, int ]`
     * @throws InvalidArgumentException
     */
    protected function createBinds(string &$types): array
    {
        $_types = [];
        $binds = [];

        $numeric = null;

        foreach ($this->params as $key => [$value, $type]) {
            // Determine if we're in numeric or keyed mode.
            if ($numeric === null) {
                $numeric = is_int($key);
            }
            // Get angry if we're mixing types.
            else if (is_int($key) !== $numeric) {
                throw new InvalidArgumentException('Mixed numeric and named params');
            }

            // Determine the bind type, it's just strings or ints really.
            switch ($type) {
                case PDO::PARAM_BOOL:
                case PDO::PARAM_INT:
                    $type = 'i';
                    break;

                case PDO::PARAM_STR:
                default:
                    $type = 's';
                    break;
            }

            // Numeric binds are pretty straight-forward.
            if ($numeric) {
                $_types[] = $type;
                $binds[] = $value;
            }
            else {
                $found = false;

                // Find the (multiple) positions of the named parameter. This
                // determines order in which to bind it. We bind more than once
                // because although the values are only provided once, the key
                // may be used multiple times in the query.
                foreach ($this->named as $position => $name) {
                    if ($name != $key) continue;

                    $found = true;
                    $_types[$position] = $type;
                    $binds[$position] = $value;
                }

                if (!$found) {
                    throw new InvalidArgumentException("Unknown param: {$key}");
                }
            }
        }

        // The binds are inserted by their position in the query. PHP naturally
        // sorts them by insertion, so re-sort and strip the old keys to get a
        // neat and ordered list.
        if (!$numeric) {
            ksort($binds);
            ksort($_types);

            $binds = array_values($binds);
        }

        $types = implode('', $_types);
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
        // Override params.
        if ($params) {
            $copy = clone $this;
            $copy->params = $params;
            return $copy->execute();
        }
        else {
            // Bind params, but optionally.
            $types = '';
            $binds = $this->createBinds($types);

            if ($types) {
                $this->stmt->bind_param($types, ...$binds);
            }

            // Note, we're allowed to emit mysqli exceptions from here, these
            // are caught at the Pdb level along with PDO exceptions.
            // These are all converted into Pdb exceptions.
            return $this->stmt->execute();
        }
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
    public function fetchColumn(int $column = 0)
    {
        $result = $this->openCursor();
        $row = $result->fetch_array(MYSQLI_NUM);
        return $row[$column] ?? false;
    }


    /** @inheritdoc */
    public function columnCount(): int
    {
        return $this->stmt->field_count;
    }


    /** @inheritdoc */
    public function rowCount(): int
    {
        return $this->stmt->num_rows ?: $this->stmt->affected_rows;
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
