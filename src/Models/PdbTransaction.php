<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\DataObject;
use karmabunny\pdb\Pdb;

/**
 * This represents a transaction or savepoint.
 *
 * @package karmabunny\pdb
 */
class PdbTransaction extends DataObject
{

    /** @var Pdb */
    public $pdb;

    /** @var string|false */
    public $parent;

    /** @var string|false */
    public $key;


    /**
     * Is this a savepoint?
     *
     * @return bool
     */
    public function isSavepoint(): bool
    {
        return $this->parent !== $this->key;
    }


    /**
     * Is this transaction active?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if (!$this->pdb->inTransaction()) {
            return false;
        }

        return $this->key !== false;
    }


    /**
     * Commit the transaction or savepoint.
     *
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->key === false) {
            return false;
        }

        $this->pdb->commit($this->key);

        if ($this->key === $this->parent) {
            $this->parent = false;
        }

        $this->key = false;
        return true;
    }


    /**
     * Rollback the transaction or savepoint.
     *
     * @return bool
     */
    public function rollback(): bool
    {
        if ($this->key === false) {
            return false;
        }

        $this->pdb->rollback($this->key);

        if ($this->key === $this->parent) {
            $this->parent = false;
        }

        $this->key = false;
        return true;
    }
}
