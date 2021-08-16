<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use karmabunny\kb\Collection;
use karmabunny\kb\Uuid;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\TransactionRecursionException;
use PDOException;

/**
 * This implements an complete version of {@see PdbModelInterface}.
 *
 * It includes audit fields, soft deletes and UUIDv5.
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
    public function getDateModified()
    {
        return new DateTimeImmutable($this->date_modified);
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
     * Generate an appropriate UUID.
     *
     * Beware - new records are created with a UUIDv4 while the save() method
     * generates a UUIDv5. Theoretically this shouldn't be externally apparent
     * due to the wrapping transaction.
     *
     * @return string
     * @throws Exception
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
     * Save this model.
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     * @throws Exception
     * @throws TransactionRecursionException
     * @throws PDOException
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

            // TODO Add shared transaction support.
            $ts_id = 0;
            if (!$pdb->inTransaction()) {
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

        return (bool) $this->id;
    }


    /**
     * Delete this model.
     *
     * @param bool $soft 'Delete' without removing the record.
     * @return bool
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
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
