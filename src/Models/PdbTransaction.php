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
    public $key;


    /**
     * Commit the transaction or savepoint.
     *
     * @return void
     */
    public function commit()
    {
        if (!$this->key) return;

        $this->pdb->commit($this->key);
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
        $this->key = false;
    }
}