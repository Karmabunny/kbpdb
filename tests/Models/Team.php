<?php

namespace kbtests\Models;

use karmabunny\pdb\Attributes\PdbColumn;
use karmabunny\pdb\Attributes\PdbEnum;

class Team extends Model
{

    public static function getTableName(): string
    {
        return 'teams';
    }

    #[PdbColumn('VARCHAR(200)')]
    public $name;

    #[PdbEnum(['new', 'active', 'retired'])]
    public $status;

    #[PdbColumn('DATETIME')]
    public $founded;
}
