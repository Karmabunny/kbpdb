
## PDB

A PDO wrapper for fun and profit.

This is ripped directly from Sprout 3 with a lot of modification. There's a set of (in-progress) compat classes to enable one to 'backport' the updates here into a Sprout site.

General improvements are things like:
- consistent db-agnostic column escapes
- query builder
- models
- improved dbsync
- migration exports
- adapter architecture for other DBs (postgres, sqlite, mssql)
- more tests


## Install

Add as a dependency:

```sh
composer require karmabunny/pdb
```


## Example

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

Docs:
- class map (w/ complete doc comments)
- how to use pdb query
- guide to conditions syntax
- installing the compatibility layer
- Building your own PdbModel
- UUID building
- how to not use SQL_CALC_FOUND_ROWS (and why)

Tests:
- utilities
- Pdb
- PdbQuery
  - SQL building
  - executors
- PdbSync

performance tracing

remove PdbModel? (too specific, move it to Bloom I guess)

Adapters:
- rename drivers -> adapters
- add postgres adapter
- sqlite dependent keys

PdbCondition:
- make everything use named binds
- some sort of magical numeric to number binds thingy

PdbQuery:
- select should be keyed by alias
- orderBy() and groupBy() should accept strings
- add addParams() to (after adding enforced named binds)

Schema stuff:
- add 'autoinc-start' per table
- add 'secret=1|0' per column
  - Records a 'secret' tag in the column comment
  - Informs export behaviour
- put db_struct.xsd somewhere online
- tie down boolean properties to 0|1

Refactor:
- rename extended methods (fieldList, indexList)

New stuff:
- should return row keys lower-cased? via config?
- `Pdb::savepoint()` for nested transactions
- `Pdb::transact()` should return a transaction ID
  - commit() requires this ID as an arg
  - forces the user to manage the transaction lifecycle
  - rollback prevents any commits
    - throws an error? returns bool?
  - can do nested transaction via savepoint()

Fixes:
- Pdb::find() should respect 'date_deleted'
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

