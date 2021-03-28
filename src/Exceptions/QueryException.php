<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Exception;
use PDOException;

/**
 * Exception thrown when a database query fails, or gives an empty result set
 * for a query which required a row to be returned
 */
class QueryException extends Exception
{

    /** @var string */
    public $query;

    /** @var array */
    public $params;

    /**
     * The SQLSTATE error code associated with the failed query.
     * See e.g.:
     * https://en.wikibooks.org/wiki/Structured_Query_Language/SQLSTATE
     * https://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
     * https://msdn.microsoft.com/en-us/library/ms714687.aspx
     *
     * @var string
     */
    public $state;


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
     * @param PDOException $exception
     * @return QueryException
     */
    public static function create(PDOException $exception)
    {
        $class = self::getSubClass($exception);
        return (new $class($exception->getMessage(), 0, $exception))
            ->setState($exception->getCode() ?: '00000');
    }


    /**
     *
     * @param PDOException $exception
     * @return string
     */
    protected static function getSubClass(PDOException $exception)
    {
        $state_class = substr($exception->getCode(), 0, 2);

        switch ($state_class) {
            case '23':
                return ConstraintQueryException::class;

            case '00':
            default:
                return QueryException::class;
        }
    }

}
