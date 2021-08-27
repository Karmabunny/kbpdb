<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when a database query encounters a transaction errors.
 *
 * These errors are reported as SQLSTATE 25xxx
 */
class TransactionQueryException
    extends QueryException
    implements TransactionException
{
}
