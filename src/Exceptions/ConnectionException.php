<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

/**
 * Exception thrown when a connection fails.
 */
class ConnectionException extends PdbException
{

    /** @var string */
    public $dsn;


    /**
     * @param string $dsn
     * @return static
     */
    public function setDsn(string $dsn): static
    {
        $this->dsn = $dsn;
        $this->message .= " ({$dsn})";
        return $this;
    }
}
