<?php

namespace karmabunny\pdb\Models;

use IteratorAggregate;
use PDO;
use Traversable;

/**
 * PDO compatible statements.
 *
 * These aren't strictly (nominally) compatible with PDO, as such you can't
 * pass these into any PDO helpers directly, but Pdb will happily accept and
 * use them in place of a PDOStatement.
 *
 * You could say it's structurally compatible.
 *
 * This is used to wrap mysqli statements.
 *
 * @package karmabunny\pdb
 */
abstract class PdbStatement implements IteratorAggregate
{
    /** @var string */
    public $queryString;


    protected $fetchMode = PDO::FETCH_BOTH;


    /**
     *
     * @param int $mode
     * @return void
     */
    public function setFetchMode(int $mode)
    {
        $this->fetchMode = $mode;
    }


    /**
     *
     * @param string|int $param
     * @param mixed $var
     * @param int $type
     * @return bool
     */
    public abstract function bindValue($param, $var, int $type = PDO::PARAM_STR): bool;


    /**
     *
     * @param array $params
     * @return bool
     */
    public abstract function execute(array $params = []): bool;


    /**
     *
     * @param int $mode
     * @return mixed
     */
    public abstract function fetch(int $mode = PDO::FETCH_BOTH);


    /**
     *
     * @param int $mode
     * @return array
     */
    public abstract function fetchAll(int $mode = PDO::FETCH_BOTH): array;


    /**
     *
     * @return int
     */
    public abstract function columnCount(): int;


    /**
     *
     * @return int
     */
    public abstract function rowCount(): int;


    /**
     *
     * @return void
     */
    public abstract function closeCursor();
}
