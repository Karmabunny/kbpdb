<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use PDOException;

/**
 * Exception thrown when a connection fails.
 */
class ConnectionException extends PdbException
{

    /** @var string */
    public $dsn;


    /** @inheritdoc */
    public static function create(PDOException $exception)
    {
        $exception = parent::create($exception);

        // Special messages for mysql to make things 'clearer'.
        strtr($exception->message, [
            'No such file or directory' => 'Fix your database hostname ya goose',
        ]);

        return $exception;
    }


    /**
     * @param string $dsn
     * @return static
     */
    public function setDsn(string $dsn)
    {
        $this->dsn = $dsn;
        $this->message .= " ({$dsn})";
        return $this;
    }
}
