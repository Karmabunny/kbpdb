<?php

namespace kbtests\Models;


/**
 * And then most models will look like this. Neat and Tidy.
 */
class Club extends Model
{

    public static function getTableName(): string
    {
        return 'clubs';
    }

    public $name;

    public $status;

    public $founded;
}
