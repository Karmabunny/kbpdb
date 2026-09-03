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
 * Functions:
 *  - `'create'`      - create table, update table attributes
 *  - `'primary'`     - update primary key
 *  - `'column'`      - create/modify columns
 *  - `'index'`       - update indexes
 *  - `'foreign_key'` - update constraints
 *  - `'remove'`      - remove columns
 *  - `'views'`       - process views
 *
 * @package karmabunny\pdb
 */
class SyncActions extends Collection
{
    public bool $create = true;

    public bool $primary = true;

    public bool $column = true;

    public bool $index = true;

    public bool $foreign_key = true;

    public bool $remove = true;

    public bool $views = true;
}
