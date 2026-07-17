<?php
declare(strict_types=1);
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
class PdbException extends Exception implements PdbExceptionInterface
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
    public string $state = '';


    /**
     *
     * @param string|int $state
     * @return static
     */
    public function setState(string|int $state): static
    {
        $this->state = (string) $state;
        return $this;
    }


    /**
     *
     * @param PDOException $exception
     * @return static
     */
    public static function create(PDOException $exception): static
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
    protected static function getSubClass(PDOException $exception): string
    {
        return static::class;
    }
}
