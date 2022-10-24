<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Exception;
use mysqli;
use mysqli_sql_exception;
use PDO;
use PDOException;

/**
 *
 */
class PdbException extends Exception
{

    /**
     * The SQLSTATE error code associated with the failed query.
     *
     * See:
     * https://en.wikipedia.org/wiki/SQLSTATE
     * https://en.wikibooks.org/wiki/Structured_Query_Language/SQLSTATE
     * https://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
     * https://msdn.microsoft.com/en-us/library/ms714687.aspx
     *
     * @var string
     */
    public $state;


    /**
     *
     * @param string $state
     * @return $this
     */
    public function setState(string $state)
    {
        $this->state = $state;
        return $this;
    }


    /**
     *
     * @param PDOException|mysqli_sql_exception $exception
     * @param PDO|mysqli $db
     * @return static
     */
    public static function create($exception, $db = null)
    {
        $class = static::class;
        $state = self::getState($exception, $db);
        return (new $class($exception->getMessage(), 0, $exception))
            ->setState($state);
    }


    /**
     *
     * @param PDOException|mysqli_sql_exception $exception
     * @param PDO|mysqli $db
     * @return string
     */
    protected static function getState($exception, $db = null): string
    {
        if ($exception instanceof PDOException) {
            return $exception->getCode() ?: '00000';
        }

        if ($db and $exception instanceof mysqli_sql_exception) {
            return $db->sqlstate;
        }

        return '00000';
    }
}
