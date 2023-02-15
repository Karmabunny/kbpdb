<?php

namespace kbtests\Models;

use karmabunny\pdb\Pdb;

/**
 * Base models from Sprout.
 *
 * It's not entirely important that this is 1-to-1 with Sprout - we're simply
 * testing the 'internalSave' mechanism.
 */
abstract class SproutModel extends Record
{

    /** @inheritdoc */
    public function getSaveData(): array
    {
        $data = parent::getSaveData();

        $pdb = static::getConnection();
        $table = static::getTableName();
        $now = Pdb::now();

        // Include the uuid if it's not already set.
        // This may return NIL, that's OK - we do an insert + update later.
        if (empty($data['uid']) and property_exists($this, 'uid')) {
            $data['uid'] = $pdb->generateUid($table, (int) $this->id);
        }

        if (property_exists($this, 'date_modified')) {
            $data['date_modified'] = $now;
        }

        if (!$this->id) {
            if (property_exists($this, 'date_added')) {
                $data['date_added'] = $now;
            }
        }

        return $data;
    }


    /** @inheritdoc */
    protected function _internalSave(array &$data)
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        if ($this->id > 0) {
            $pdb->update($table, $data, [ 'id' => $this->id ]);
        }
        else {
            $data['id'] = $pdb->insert($table, $data);

            // Now generate a real uuid.
            if (property_exists($this, 'uid')) {
                $data['uid'] = $pdb->generateUid($table, $data['id']);

                $pdb->update(
                    $table,
                    [ 'uid' => $data['uid'] ],
                    [ 'id' => $data['id'] ]
                );
            }
        }
    }


    /** @inheritdoc */
    protected function _afterSave(array $data)
    {
        parent::_afterSave($data);

        if (
            property_exists($this, 'date_modified')
            and isset($data['date_modified'])
        ) {
            $this->date_modified = $data['date_modified'];
        }

        if (
            property_exists($this, 'date_added')
            and isset($data['date_added'])
        ) {
            $this->date_added = $data['date_added'];
        }

        if (
            property_exists($this, 'uid')
            and isset($data['uid'])
        ) {
            $this->uid = $data['uid'];
        }
    }

}
