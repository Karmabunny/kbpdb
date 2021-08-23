
## PDB


```php

$config = include __DIR__ . '/database.php';
$pdb = Pdb::create($config['default']);

// Full query
$res = (new PdbQuery($pdb))
    ->select('id', 'name')
    ->from('clubs')
    ->where(['status' => 'active'])
    ->map();

// Raw mode
$res = (new PdbQuery($pdb))
    ->sql("SELECT id, name
            FROM ~clubs
            WHERE status = ?",
        ['active'])
    ->map();

// Shorthand
// - Find combines from() + where()
// - map() can accept two parameters
$res = (new PdbQuery($pdb))
    ->find('clubs', ['status' => 'active'])
    ->map('id', 'name');

// Classic
$q = "SELECT id, name
    FROM ~clubs
    WHERE status = ?";
$params = ['active'];
$res = $pdb->query($q, $params, 'map');

// Transactions
$pdb->transact();
$pdb->commit();
$pdb->rollback();

// Alternatives
$res = $pdb->select('id', 'name')->from('clubs')->all(100);
$res = $pdb->find('clubs', ['status' => 'active'])->keyed('id');
$res = $pdb->count('clubs', ['status' => 'active']);


// This a model based on \kb\Collection
class ClubModel extends PdbModel
{

    // Create one here, or cache it wherever you please.
    protected static function getConnection(): Pdb
    {
        return Pdb::create($config['default']);
    }

    // Where this model is stored.
    public static function getTableName(): string
    {
        return 'clubs';
    }

    // Implicit properties (from PdbModelTrait):
    // - id
    // - uid
    // - active
    // - date_added
    // - date_modified
    // - date_deleted

    public $name;

    public $status;
}

// Get a model.
$thing = ClubModel::find(['id' => 123])->one();

// Update the model.
$thing->name = 'east boat club';
$thing->save();

// Delete the model.
$thing->delete();

// Create a model.
$other = new ClubModel();
$other->name = 'west sailing club';
$other->active = true;
$other->save();

```


### TODOs

More docs. More comments. More tests.

Tests:
- utilities
- Pdb
- PdbQuery
  - SQL building
  - executors
- PdbSync

performance tracing

Schema stuff:
- add 'autoinc-start' per table
- add 'secret=1|0' per column
  - Records a 'secret' tag in the column comment
  - Informs export behaviour
- put db_struct.xsd somewhere online
- tie down boolean properties to 0|1

Compat:
- finish sprout compatibility stuff

Refactor:
- rename extended methods (fieldList, indexList)

New stuff:
- should return row keys lower-cased? via config?
- `Pdb::transact()` should return a transaction ID
  - commits can only be done with this ID
  - forces the user to manage the transaction lifecycle
  - rollback prevents any commits
    - throws an error? returns bool?

Fixes:
- Pdb::find() should respect 'date_deleted'
- select should be keyed by alias
- sync doesn't remove indexes when renaming a table (needs additional sync)

Document UUIDs somewhere:
- 'PDB' has a single namespace (uuidv4)
- each table then hashes that with 'schema.table.id'
- eg. 'twee.users.123'

Maybe:
- Make UUID v4 or v5 toggle-able. Maybe.

Uuid 4:
 - always unique
 - not deterministic

Uuid 5:
 - deterministic, hurrah!
 - Although not reversible (ID -> UUID, UUID !-> ID)
 - But requires two writes per new record

