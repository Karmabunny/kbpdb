<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Throwable;

/**
 * Just an interface to match both query and recursion type transaction errors.
 */
interface TransactionException extends Throwable
{
}
