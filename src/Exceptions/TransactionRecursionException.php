<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when an attempt is made to start a transaction from within an existing transaction
 */
class TransactionRecursionException
    extends TransactionQueryException
    implements TransactionException
{
    public $state = '25001';
}
