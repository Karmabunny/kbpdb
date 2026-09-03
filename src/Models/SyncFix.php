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
class SyncFix extends Collection
{
    public string $name;

    public string $sql;
}
