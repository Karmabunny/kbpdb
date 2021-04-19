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
     * @return string A subclass of QueryException
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
