<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when an invalid transaction/savepoint name is used.
 *
 * This typically occurs when attempting to commit or rollback a savepoint
 * that doesn't exist.
 *
 * This can also occur in 'strict transactions' mode if a commit() is called
 * without a valid id.
 */
class TransactionNameException
    extends PdbException
    implements TransactionException
{
}
