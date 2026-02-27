<?php
declare(strict_types=1);

namespace karmabunny\pdb;

use karmabunny\interfaces\MutexInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbMutex implements MutexInterface
{

    /** @var Pdb */
    public Pdb $pdb;

    /** @var string */
    public string $name;

    /** @var bool */
    public bool $autoRelease = true;

    /** @var bool */
    public bool $uniqueLocks = true;

    /** @var bool */
    public bool $releaseAllLocks = false;


    public function __construct(Pdb $pdb, string $name)
    {
        $this->pdb = $pdb;
        $this->name = $name;

        // Create new sessions to acquire unique locks.
        if ($this->uniqueLocks) {
            $this->pdb = Pdb::create($this->pdb->config);
        }
    }


    /** @inheritdoc */
    public function __destruct()
    {
        if ($this->autoRelease) {
            $this->release();
        }
    }


    /** @inheritdoc */
    public function acquire(float $timeout = 0): bool
    {
        return $this->pdb->createLock($this->name, $timeout);
    }


    /** @inheritdoc */
    public function release(): bool
    {
        $ok = $this->pdb->deleteLock($this->name);

        // Release all locks.
        if ($ok and $this->releaseAllLocks) {
            while ($this->pdb->deleteLock($this->name));
        }

        return $ok;
    }
}
