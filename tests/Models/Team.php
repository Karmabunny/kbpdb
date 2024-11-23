<?php

namespace kbtests\Models;

use karmabunny\pdb\Attributes\PdbColumn;
use karmabunny\pdb\Attributes\PdbEnumColumn;
use karmabunny\pdb\Attributes\PdbForeignKey;
use karmabunny\pdb\Attributes\PdbIndex;
use karmabunny\pdb\Attributes\PdbPreviousNames;
use karmabunny\pdb\Attributes\PdbPrimaryKey;
use karmabunny\pdb\Attributes\PdbTable;
use kbtests\Attributes\CategoriesTable;

#[PdbTable]
#[PdbPreviousNames(['groups'])]
#[PdbIndex(['status'])]
#[CategoriesTable]
class Team extends Model
{

    public static function getTableName(): string
    {
        return 'teams';
    }

    #[PdbPrimaryKey(true)]
    public $id;

    #[PdbColumn('VARCHAR(200)')]
    public $name;

    #[PdbEnumColumn(['new', 'active', 'retired'])]
    public $status;

    #[PdbColumn('DATETIME')]
    #[PdbPreviousNames(['created'])]
    public $founded;

    #[PdbColumn('INT UNSIGNED')]
    #[PdbForeignKey('clubs', 'id', 'cascade', 'cascade')]
    public $club_id;
}
