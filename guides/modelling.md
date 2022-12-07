
## Modelling databases with PDB

```py
import test from ok
```

```php
use karmabunny\kb\Collection;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModelInterface;
use karmabunny\pdb\PdbModelTrait;


class ClubModel extends Collection implements PdbModelInterface
{
    use PdbModelTrait;

    // Create one here, or cache it wherever you please.
    protected static function getConnection(): Pdb
    {
        return Pdb::create($config['default']);
    }

    // Where this model is stored, non-prefixed.
    public static function getTableName(): string
    {
        return 'clubs';
    }

    // A column in 'clubs'.
    public $name;

    // Another column in 'clubs'.
    public $status;

    // A FK columns perhaps.
    public $region_id;


    // Return a related model.
    public function getRegion(): Region
    {
        return Region::find(['id' => $this->region_id])->one();
    }
}

// Get a model.
$thing = ClubModel::find(['id' => 123])->one();

// Update the model.
$thing->name = 'east boat club';
$thing->save();

// Delete the model.
$thing->delete();

// Create a model.
$other = new ClubModel([
    'name' => 'west sailing club',
]);
$other->save();
```
