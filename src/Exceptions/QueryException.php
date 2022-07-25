<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use mysqli;
use mysqli_sql_exception;
use PDO;
use PDOException;

/**
 * Exception thrown when a database query fails, or gives an empty result set
 * for a query which required a row to be returned
 */
class QueryException extends PdbException
{

    /** @var string */
    public $query;

    /** @var array */
    public $params;


    /**
     *
     * @param string $query
     * @return static
     */
    public function setQuery(string $query)
    {
        $this->query = $query;
        $this->message .= ' query was: ' . $query;
        return $this;
    }


    /**
     *
     * @param array $params
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }


    /**
     *
     * @param PDOException|mysqli_sql_exception $exception
     * @param PDO|mysqli $db
     * @return QueryException
     */
    public static function create($exception, $db = null)
    {
        // This CANNOT call parent::create() because it uses a static
        // class builder.
        $state = self::getState($exception, $db);
        $class = self::getSubClass($state);
        return (new $class($exception->getMessage(), 0, $exception))
            ->setState($state);
    }


    /**
     *
     * @param string $state
     * @return string A subclass of QueryException
     */
    protected static function getSubClass(string $state)
    {
        $state_class = substr($state, 0, 2);

        switch ($state_class) {
            case '22':
                return DataQueryException::class;

            case '23':
                return ConstraintQueryException::class;

            case '25':
                return TransactionQueryException::class;

            case '00':
            default:
                return QueryException::class;
        }
    }
}
