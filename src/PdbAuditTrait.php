<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DateTimeImmutable;

/**
 * This class has audit fields.
 *
 * The PdbTrait will insert these when create/updating/deleting.
 *
 * @package karmabunny\pdb
 */
trait PdbAuditTrait
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


    /**
     *
     * @return DateTimeInterface|null
     */
    public function getDateAdded()
    {
        if ($this->date_added) {
            new DateTimeImmutable($this->date_added);
        }
        return null;
    }


    /**
     *
     * @return DateTimeInterface|null
     */
    public function getDateUpdated()
    {
        if ($this->date_updated) {
            new DateTimeImmutable($this->date_updated);
        }
        return null;
    }


    /**
     *
     * @return DateTimeInterface|null
     */
    public function getDateDeleted()
    {
        if ($this->date_deleted) {
            new DateTimeImmutable($this->date_deleted);
        }
        return null;
    }
}
