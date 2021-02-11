<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when a database query fails due to an integrity constraint violation
 * These errors are reported as SQLSTATE 23xxx
 */
class ConstraintQueryException extends QueryException
{
}
