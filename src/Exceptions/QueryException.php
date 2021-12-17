<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

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
     * @return string A subclass of QueryException
     */
    protected static function getSubClass(PDOException $exception)
    {
        $state_class = substr($exception->getCode(), 0, 2);
        [ , $driver_code ] = $exception->errorInfo ?? [0, 0];

        // MySQL reports savepoints errors as a syntax 42000.
        // TODO should probably check the adapter here too.
        if ($state_class == 42 and $driver_code == 1305) {
            $state_class = '3B';
        }

        switch ($state_class) {
            case '22':
                return DataQueryException::class;

            case '23':
                return ConstraintQueryException::class;

            case '3B':
                return TransactionNameException::class;

            case '25':
                // TODO
                // - 25001 and 25002 => TransactionRecursionException
                // - 25005 => TransactionEmptyException
                return TransactionQueryException::class;
        }

        parent::getSubClass($exception);
    }
}
