
## Modelling databases with PDB


### Basic implementation

The core essential for a PDB model is to implement `PdbModelInterface`. However the library provides the `PdbModelTrait` utility to fill in the boilerplate.

When implementing you model class one only needs to care about the first two, `getConnection()` and `getTableName()`. The ideal model layout, used by Sprout and others is below:

```php
// 1. This is a base class that defines the database connection for all models.
abstract class Model implements PdbModelInterface
{
    use PdbModelTrait;

    public static function getConnection(): Pdb
    {
        // 2. Ideally your app shares a common connection during the whole request.
        return MyApp::createConnection();
    }
}

class TargetModel extends Model
{
    // 3. Define your column properties.
    public $name;

    // 4. Define your table name.
    public static function getTableName(): string
    {
        return 'targets';
    }
}
```

This isn't gospel, however. If you want your base model to generate the appropriate table name or perhaps if you want to override the connection per model type, go ahead.


### Saving Models

The save method is relatively straightforward, INSERT for new object, UPDATE for existing ones. A simple example would look like this:

```php
public function save(): bool
{
    $pdb = static::getConnection();
    $table = static::getTableName();

    // Careful, you might want only public fields.
    // Alternatively use karmabunny/kb/Reflect::getProperties().
    $data = object_get_vars($this);

    if ($this->id) {
        $pdb->update($table, $data, ['id' => $this->id]);
    }
    else {
        $this->id = $pdb->insert($table, $data);
    }

    return (bool) $this->id;
}
```

Using the `PdbModelTrait` this can get far more interest. By default the trait will wrap your `save()` with a transaction and populates default fields. The process is broken down into a series of hooks to permit one to neatly override different aspects of the process. Possibly app-wide or even per model.

The rules of mutation between these hooks is important to retain a consistent state between the object and the database. Should the INSERT/UPDATE fail and the transaction is rolled back - the object should not be mutated. Only have a successful commit should the object mutate from generated data.

The exception being `_beforeSave()` - this mutates at will. That said, be careful what mutations are performed - if any.

Hooks, in order of execution:

1. `_beforeSave(): void`
2. `getSaveData(): array`
3. `_internalSave(array &$data): void`
4. `_afterSave($data): void`


> `_beforeSave(): void`

Use this to perform mutations on the object before saving.

By default this applies default values from the table spec.


> `getSaveData(): array`

Get the object data to be committed into the database. This is the place to create generated fields like 'date_modified' or UUIDs. From this point the model object should _not_ mutated until the `_afterSave()` hook.

By default this returns all _public_ field.


> `_internalSave(&$data): void`

Insert or update the database entity. Override this to retain transactional and hook behaviour.

Mutations can be performed on the `$data` parameter but _not_ the object model. These mutations should be applied in the next `_afterSave()` hook.


> `_afterSave($data): void`

This is called after a successful transaction commit. At this point the data object (from `getSaveData()` and `_internalSave()`) should be stored back into the model object.



### Query building


`PdbQuery::as($class)`

TODO


`PdbModelQuery`

TODO


### Model Query Builders

A common pattern is to extend the query builder `PdbModelQuery` for each model, or even a set of common models. This permits one to add complex query functionality that is explicit for the given model(s).

A simple example:

```php
class ClubModelQuery extends PdbModelQuery
{
    protected $_dirty = false;
    protected $_founded = null;

    // Filter between founded and disband dates.
    public function wasActive(DateTimeInterface $date)
    {
        $this->_founded = $date;
        $this->_dirty = true;
        return $this;
    }

    public function build(): array
    {
        if (!$this->_dirty) {
            return parent::build();
        }

        // Clone the query - build() shouldn't modify it's own clauses.
        // Unset dirty bit and filters so we don't end up in a loop.
        $query = clone $this;
        $query->_dirty = false;
        $query->_founded = null;

        $query->andWhere([
            ['date_found', '>=', $query->_founded],
            ['date_disband', '<=', $query->_founded],
        ]);

        return $query->build();
    }
}
```


TODO update PdbQuery to handle 'dirty' better.



### Example

```php
use karmabunny\kb\Collection;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbModelInterface;
use karmabunny\pdb\PdbModelTrait;


class Club extends Collection implements PdbModelInterface
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
$thing = Club::find(['id' => 123])->one();

// Update the model.
$thing->name = 'east boat club';
$thing->save();

// Delete the model.
$thing->delete();

// Create a model.
$other = new Club([
    'name' => 'west sailing club',
]);
$other->save();
```
