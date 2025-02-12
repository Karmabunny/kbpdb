<?php

namespace kbtests\Models;

use karmabunny\kb\Uuid;
use karmabunny\pdb\Pdb;

/**
 * A base model.
 *
 * Using this library will typically require the user to create something
 * like this.
 *
 * This is a good chance to combine in Collections and other helpers.
 */
abstract class Model extends Record
{
    /** @var string */
    public $uid;

    /** @var string */
    public $date_added;

    /** @var string */
    public $date_modified;

    /** @var bool */
    public $active = true;


    public function getSaveData(): array
    {
        $data = parent::getSaveData();
        $now = $this->getConnection()->now();

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
        parent::_afterSave($data);

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
