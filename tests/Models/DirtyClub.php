<?php

namespace kbtests\Models;

use karmabunny\kb\PropertiesTrait;
use karmabunny\kb\Uuid;
use karmabunny\pdb\Pdb;

/**
 * And then most models will look like this. Neat and Tidy.
 */
class DirtyClub extends Model
{
    use PropertiesTrait;

    public $name;

    public $status;

    public $founded;


    /** @inheritdoc */
    public static function getTableName(): string
    {
        return 'clubs';
    }


    /** @inheritdoc */
    public function getSaveData(): array
    {
        $data = $this->getChecksums()->getAllDirty();
        unset($data['id']);

        $now = Pdb::now();

        if (!$this->id) {
            $data['date_added'] = $now;
            $data['uid'] = Uuid::uuid4();

            $defaults = self::getPropertyDefaults();
            foreach ($defaults as $name => $value) {
                if (isset($data[$name])) continue;
                $data[$name] = $value;
            }
        }

        if (!empty($data)) {
            $data['date_modified'] = $now;
        }

        return $data;
    }


    /** @inheritdoc */
    public function _afterSave(array $data)
    {
        parent::_afterSave($data);
        $this->getChecksums()->update();
    }
}
