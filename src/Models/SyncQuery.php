<?php
declare(strict_types=1);
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

    public string $heading;


    /**
     * One of PdbSync::QUERY_TYPES.
     *
     * @var string
     */
    public string $type;


    /**
     * @var string
     */
    public string $sql;


    /**
     * A message to display after the query.
     *
     * @var string
     */
    public string $message = '';
}
