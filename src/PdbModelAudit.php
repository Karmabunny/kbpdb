<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

/**
 * This class has audit fields.
 *
 * The PdbTrait will insert these when create/updating/deleting.
 *
 * @package karmabunny\pdb
 */
interface PdbModelAudit
{
    /** @var string */
    public $date_added;

    /** @var string */
    public $date_modified;

    /** @var string|null */
    public $date_deleted;

    /** @var string */
    public $uid;

    /** @var bool */
    public $active = true;
}
