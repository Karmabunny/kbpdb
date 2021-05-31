<?php
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
    /** @var bool */
    public $create = true;

    /** @var bool */
    public $primary = true;

    /** @var bool */
    public $column = true;

    /** @var bool */
    public $index = true;

    /** @var bool */
    public $foreign_key = true;

    /** @var bool */
    public $remove = true;

    /** @var bool */
    public $views = true;
}
