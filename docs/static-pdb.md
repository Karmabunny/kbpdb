
Static PDB for backwards-compatibility with Sprout.


### Migration

Despite efforts, there are some migration tasks - particularly related to `information_schema` helpers and the new models.

- getForeignKeys
- getDependentKeys
- listTables
- fieldList
- indexList

Other issues include namespace migrations. There are two options:

1. run the `sprout_migrate.sh` script over the repo.
2. write some aliasing with a `Sprout\Exceptions\` namespace.


### StaticPdb class

This class wraps everything in the `Pdb` class as best it can. Surprisingly it does a pretty ok job.

This is the wrapper used in Sprout 3.1. It provides space for a project to hack in any quick amendments but also gets any fixes from upstream PDB for free.

```php
namespace Sprout\Helpers;

use karmabunny\pdb\Compat\StaticPdb;
use karmabunny\pdb\PdbConfig;
use Kohana;

class Pdb extends StaticPdb
{
    protected static $prefix = 'sprout_';

    /** @inheritdoc */
    public static function getConfig(string $name = null): PdbConfig
    {
        $name = $name ?? 'default';
        $config = Kohana::config('database.' . $name);

        $conf = $config['connection'];
        $conf['type'] = str_replace('mysqli', 'mysql', $conf['type']);
        $conf['character_set'] = $config['character_set'];
        $conf['prefix'] = $config['prefix'] ?? self::$prefix;
        $conf['hacks'] = $config['hacks'] ?? [];

        return new PdbConfig($conf);
    }
}
```
