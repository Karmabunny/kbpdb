<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Exception;
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
     * @param PDOException $exception
     * @return static
     */
    public static function create(PDOException $exception)
    {
        $class = static::getSubClass($exception);
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
        return static::class;
    }
}
