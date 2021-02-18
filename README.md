
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
