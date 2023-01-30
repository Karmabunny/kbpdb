<?php

namespace kbtests\Models;

use karmabunny\kb\Collection;
use karmabunny\kb\Uuid;
use kbtests\Database;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModelInterface;
use karmabunny\pdb\PdbModelTrait;

/**
 * A base model.
 *
 * Using this library will typically require the user to create something
 * like this.
 *
 * This is a good chance to combine in Collections and other helpers.
 */
abstract class Model extends Collection implements PdbModelInterface
{
    use PdbModelTrait {
        getSaveData as private _getSaveData;
    }

    /** @var string */
    public $uid;

    /** @var string */
    public $date_added;

    /** @var string */
    public $date_modified;

    /** @var bool */
    public $active = true;


    /** @inheritdoc */
    public static function getConnection(): Pdb
    {
        return Database::getConnection();
    }


    public function getSaveData(): array
    {
        $data = $this->_getSaveData();
        $now = Pdb::now();

        if (!$this->id) {
            $data['date_added'] = $now;
            $data['uid'] = Uuid::uuid4();
        }

        if (!empty($data)) {
            $data['date_modified'] = $now;
        }

        return $data;
    }


    protected function _afterSave(array $data)
    {
        if (isset($data['date_added'])) {
            $this->date_added = $data['date_added'];
        }

        if (isset($data['uid'])) {
            $this->uid = $data['uid'];
        }

        if (isset($data['date_modified'])) {
            $this->date_modified = $data['date_modified'];
        }
    }

}
