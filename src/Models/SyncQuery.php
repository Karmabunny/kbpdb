<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class SyncQuery extends Collection
{

    /** @var string */
    public $heading;


    /**
     * One of PdbSync::QUERY_TYPES.
     *
     * @var string
     */
    public $type;


    /**
     * @var string
     */
    public $sql;


    /**
     * A message to display after the query.
     *
     * @var string
     */
    public $message = '';
}
