<?php

namespace karmabunny\pdb\Models;

use karmabunny\kb\DataObject;
use karmabunny\pdb\Pdb;

/**
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
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if (!$this->key) {
            return false;
        }

        return $this->pdb->inTransaction();
    }


    /**
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
