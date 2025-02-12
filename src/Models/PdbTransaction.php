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
     * Commit the transaction or savepoint.
     *
     * @return void
     */
    public function commit()
    {
        if (!$this->key) return;

        $this->pdb->commit($this->key);

        if ($this->key === $this->parent) {
            $this->parent = false;
        }

        $this->key = false;
    }


    /**
     * Rollback the transaction or savepoint.
     *
     * @return void
     */
    public function rollback()
    {
        if (!$this->key) return;

        $this->pdb->rollback($this->key);

        if ($this->key === $this->parent) {
            $this->parent = false;
        }

        $this->key = false;
    }
}
