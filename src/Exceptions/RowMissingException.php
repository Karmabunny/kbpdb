<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;


/**
 * Exception thrown when a database query gives an empty result set for a
 * query which required a row to be returned
 */
class RowMissingException extends QueryException
{
}
