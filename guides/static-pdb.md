
# Static PDB

Static PDB for backwards-compatibility with Sprout.


### Migration

Despite efforts, there are some migration tasks - particularly related to `information_schema` helpers and the new models.

- getForeignKeys
- getDependentKeys
- listTables
- fieldList
- indexList

Sprout 3.1+ has a set of namespace aliases to help migrate the Pdb exception classes in `Sprout\Exceptions\`. That said, prefer to use the ones from `karmabunny\pdb\Exceptions\` for new code.

If you like, there is a `scripts/sprout_migrate.sh` that will regex-replace these for you for a given project.


### StaticPdb class

This class wraps everything in the `Pdb` class as best it can. Surprisingly it does a pretty ok job. Sprout 3.1+ uses this class to provide backwards compatible Pdb support.

There are two key methods:

1. `getConfig($name): PdbConfig`

This initialises the static instance with a config from your application. This is the minimum to implement the static wrapper.

2. `getInstance($type): Pdb`

This fetches the static instance for the given 'type' - which is passed into `getConfig()`. This is important for access scopes and override connections but is also convenient for getting access to the instance for other things such as PdbModels.

There is a bit of silly business that converts a type `'RO' => 'read_only'` and `'RW' => 'default'`. This is just how old Sprout behaves, so this behaves the same. The default access type is `'RW'`.


#### An example

```php
namespace Sprout\Helpers;

use karmabunny\pdb\Compat\StaticPdb;
use karmabunny\pdb\PdbConfig;

class Pdb extends StaticPdb
{
    /** @inheritdoc */
    public static function getConfig(string $name = null): PdbConfig
    {
        $name = $name ?? 'default';
        $config = include __DIR__ . "/config/database.{$name}.php";
        return new PdbConfig($config);
    }
}
```
