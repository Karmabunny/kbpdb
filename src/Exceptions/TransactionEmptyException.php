<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when no transaction is active.
 */
class TransactionEmptyException
    extends PdbException
    implements TransactionException
{
}
