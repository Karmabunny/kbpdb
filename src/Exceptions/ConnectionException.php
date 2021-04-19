<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

/**
 * Exception thrown when a database query fails, or gives an empty result set
 * for a query which required a row to be returned
 */
class ConnectionException extends PdbException
{

    /** @var string */
    public $dsn;

    public function setDsn(string $dsn)
    {
        $this->dsn = $dsn;
        $this->message .= " ({$dsn})";
        return $this;
    }
}
