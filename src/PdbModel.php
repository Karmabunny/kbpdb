<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DateTimeImmutable;
use karmabunny\kb\Collection;
use karmabunny\kb\Uuid;

/**
 *
 * @package karmabunny\pdb
 */
abstract class PdbModel extends Collection implements PdbModelInterface
{
    use PdbModelTrait;

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
     * @return DateTimeInterface
     */
    public function getDateAdded()
    {
        return new DateTimeImmutable($this->date_added);
    }


    /**
     *
     * @return DateTimeInterface
     */
    public function getDateUpdated()
    {
        return new DateTimeImmutable($this->date_updated);
    }


    /**
     *
     * @return DateTimeInterface|null
     */
    public function getDateDeleted()
    {
        if ($this->date_deleted) {
            return new DateTimeImmutable($this->date_deleted);
        }
        return null;
    }


    /**
     * Generate a
     *
     * @return string
     */
    protected function getUid()
    {
        // Start out with a v4.
        if ($this->id == 0) return Uuid::uuid4();

        // Upgrade it later with a v5.
        $pdb = static::getConnection();
        return $pdb->generateUid(static::getTableName(), $this->id);
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
            $data['date_modified'] = $now;
            $pdb->update($table, $data, $conditions);
        }
        else {
            $data['date_added'] = $now;
            $data['date_modified'] = $now;
            $data['uid'] = $this->getUid();

            if (!isset($data['active'])) {
                $data['active'] = true;
            }

            // TODO Add nested transaction support with IDs.
            $ts_id = 0;
            if ($pdb->inTransaction()) {
                $ts_id = 1;
                $pdb->transact();
            }

            $this->id = $pdb->insert($table, $data);
            $this->uid = $this->getUid();

            $pdb->update(
                $table,
                ['uid' => $this->uid],
                ['id' => $this->id]
            );

            if ($ts_id === 1) {
                $pdb->commit();
            }
        }

        $this->date_added = $data['date_added'];
        $this->date_modified = $data['date_modified'];

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
}
