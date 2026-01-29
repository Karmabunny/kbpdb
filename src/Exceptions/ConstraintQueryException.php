<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Throwable;

/**
 * Exception thrown when a database query fails due to an integrity constraint violation
 *
 * These errors are reported as SQLSTATE 23xxx
 */
class ConstraintQueryException extends QueryException
{

    /** @var string */
    protected $key_name = '(unknown)';

    /** @var string */
    protected $key_value = '(unknown)';


    /** @inheritdoc */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $matches = [];
        if (preg_match("/duplicate.*'([^']*)'.*key '([^']*)'/i", $message, $matches)) {
            $this->key_name = $matches[2];
            $this->key_value = $matches[1];
        }
    }


    /**
     * The name of the key that caused the constraint violation.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->key_name;
    }


    /**
     * The key value that caused the constraint violation.
     *
     * @return string
     */
    public function getKeyValue(): string
    {
        return $this->key_value;
    }

}
