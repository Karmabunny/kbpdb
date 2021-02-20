<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DateTimeImmutable;
use karmabunny\kb\Uuid;

/**
 *
 * @package karmabunny\pdb
 */
trait PdbModelTrait
{

    /** @var int */
    public $id = 0;

    /** @var string */
    public $uid;

    /** @var bool */
    public $active = true;

    /** @var string */
    public $date_added;

    /** @var string */
    public $date_modified;

    /** @var string|null */
    public $date_deleted;


    /**
     *
     * @return Pdb
     */
    protected abstract static function getConnection(): Pdb;


    /**
     *
     * @return string
     */
    public abstract static function getTableName(): string;


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


    /**
     *
     * @return bool
     */
    public function save(): bool
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        $now = Pdb::now();
        $data = iterator_to_array($this);
        $conditions = [ 'id' => $this->id ];

        if ($this->id > 0) {
            $data['date_modified'] = $this->date_modified = $now;
            $pdb->update($table, $data, $conditions);
        }
        else {
            $data['date_added'] = $this->date_added = $now;
            $data['date_modified'] = $this->date_modified = $now;
            $data['date_deleted'] = $this->date_deleted = null;
            $data['uid'] = $this->uid = Uuid::uuid4();

            if (!isset($this->active)) {
                $this->active = true;
            }

            $this->id = $pdb->insert($table, $data);
        }

        return $this->id;
    }


    /**
     *
     * @return bool
     */
    public function delete($soft = true): bool
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        $conditions = [ 'id' => $this->id ];

        if ($soft) {
            // Bump the modified field, right?
            $now = Pdb::now();

            $this->date_deleted = $now;
            $this->date_modified = $now;

            $data = [
                'date_modified' => $now,
                'date_deleted' => $now,
            ];
            return (bool) $pdb->update($table, $data, $conditions);
        }
        else {
            return (bool) $pdb->delete($table, $conditions);
        }
    }


    /**
     *
     * @return static
     */
    public static function find(array $conditions = [])
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        return (new PdbQuery($pdb))
            ->find($table, $conditions)
            ->as(static::class)
            ->one();
    }


    /**
     *
     * @return static[]
     */
    public static function findAll(array $conditions = [])
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        return (new PdbQuery($pdb))
            ->find($table, $conditions)
            ->as(static::class)
            ->all();
    }
}
