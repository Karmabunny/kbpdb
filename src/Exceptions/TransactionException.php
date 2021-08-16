<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown for various transaction errors.
 *
 * - DB fails to create a transaction
 * - DB has no transaction support
 * - A rollback or commit is called after a rollback
 */
class TransactionException extends PdbException
{
}
