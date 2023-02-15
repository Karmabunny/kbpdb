<?php

namespace kbtests\Models;


/**
 * A sprout model for testing 'internalSave' overrides.
 */
class SproutItem extends SproutModel
{

    /** @inheritdoc */
    public static function getTableName(): string
    {
        return 'sprout';
    }


    /** @var string */
    public $name;

    /** @var string */
    public $uid;

    /** @var string */
    public $date_added;
}
