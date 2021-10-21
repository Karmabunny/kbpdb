<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when a database query encounters a data error.
 *
 * These errors are reported as SQLSTATE 22xxx
 */
class DataQueryException extends QueryException
{

    /**
     *
     * @param string $query
     * @return static
     */
    public function setQuery(string $query)
    {
        $this->query = $query;

        // If there's a message about the failed column, there's a chance that
        // we know the value for it if the bind parameters are keyed.
        $matches = [];
        if (preg_match("/column ['\"]([^'\"]+)['\"]/", $this->message, $matches)) {
            $key = ':' . $matches[1];
            $value = $this->params[$key] ?? null;
        }

        // Include this in the message and truncate it.
        if (isset($value)) {
            $value = substr($value, 0, 30);
            $this->message .= " value was {$value}";
        }

        return $this;
    }
}
