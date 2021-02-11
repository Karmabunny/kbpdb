<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Exception;


/**
 * Exception thrown when a database query fails, or gives an empty result set
 * for a query which required a row to be returned
 */
class QueryException extends Exception
{
    public $query;
    public $params;

    /**
     * The SQLSTATE error code associated with the failed query.
     * See e.g.:
     * https://en.wikibooks.org/wiki/Structured_Query_Language/SQLSTATE
     * https://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
     * https://msdn.microsoft.com/en-us/library/ms714687.aspx
     */
    public $state;

}
