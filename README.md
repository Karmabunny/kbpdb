
## PDB


```php

$config = include __DIR__ . '/database.php';
$pdb = new Pdb($config['default']);

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


// Models
class ClubModel
{
    use PdbTrait;

    // Create one here, or cache it wherever you please.
    protected static function getPdb(): Pdb
    {
        return new Pdb($config['default']);
    }

    public $id;
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
$other = new Model();
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

Schema stuff:
- add 'autoinc-start' per table
- add 'secret=1|0' per column
  - Records a 'secret' tag in the column comment
  - Informs export behaviour
- put db_struct.xsd somewhere online
- tie down boolean properties to 0|1

Refactoring:
- Rename DatabaseSync - PdbSync
- split up DatabaseSync
  - DbSync parser
  - DbSync executer
  - DbSync _writer_...?

New stuff:
- Make DbSync output a set of SQL 'migration' files
  - provides opportunity to add migration _data_
  - execute each of one these
  - store execution history somewhere... files? table?
- `Pdb::transact()` should return a transaction ID
  - commits can only be done with this ID
  - forces the user to manage the transaction lifecycle
  - rollback prevents any commits
    - throws an error? returns bool?

Fixes:
- Pdb::find() should respect 'date_deleted'

Kinda related:
- Rewrite kbphp XML class as DOM
- use DOMDocument + DOMNode/Element everywhere
- use DOMXPath

Probably not possible, but I want to use UUIDv5 on table records.

The idea being:
- 'PDB' has a single namespace, probably some UUIDv4 or v1
- each table then hashes that with 'schema.table.id'
- eg. 'twee.users.123'

Result:
- UUIDs are now deterministic, hurrah!
- But requires two writes per new record

