
## Configuration

This config is detailed in the the `PdbConfig` object.


| name           | type                         | description                                    | default                   |
|----------------|------------------------------|------------------------------------------------|---------------------------|
| type           | `enum`                       | database type                                  | -                         |
| host           | `string`                     | hostname or IP address                         | -                         |
| user           | `string`                     | connection username                            | -                         |
| pass           | `string`                     | connection password                            | -                         |
| database       | `string`                     | database name                                  | -                         |
| port           | `int`                        | port number, or null for default (per driver)  | null                      |
| schema         | `string`                     | postgres schema                                | public                    |
| prefix         | `string`                     | global table prefix                            | pdb_                      |
| character_set  | `string`                     | connection charset                             | utf8                      |
| namespace      | `string`                     | for UUIDv5 generation                          | `Pdb::UUID_NAMESPACE`     |
| table_prefixes | `[table => prefix]`          | per-table prefixes                             | -                         |
| formatters     | `[class-string => callable]` | string formatter for class parameters          | -                         |
| dsn            | string                       | connection string override                     | generated                 |
| session        | `[name => value]`            | connection session variables                   | -                         |
| hacks          | `string[]`                   | driver specific hacks                          | -                         |
| cache          | configurable                 | a cache extending `PdbCache`                   | `PdbStaticCache`          |
| inflector      | configurable                 | a inflector implementing `InflectorInterface`  | `karmabunny\kb\Inflector` |
| identity       | `string`                     | distinguishes between connections with a cache | `sha($dsn)`               |
| ttl            | `int`                        | caching time-to-live, in seconds               | 10                        |


### TYPE Enum

 - mysql (full support)
 - sqlite (partial)
 - pgsql (partial)
 - mssql (untested)
 - oracle (untested)


### Configurable

These parameters can be an instance of config for a 'Configurable' class type. The type union looks like this: `string|array|object`.

`PdbStaticCache` is a default capable configurable, so a class-string will configure just fine.

The array syntax provides a configuration for the given class, like so:

```php
[PdbRedisCache::class => [
    'host' => 'localhost',
]]
```

Finally, the object is simply validated as an appropriate subclass and passed through.


### MySQL Character sets

Character sets for MySQL are a tortured story. Since at least 2002 the default has been 'utf8' for which 'utf8mb3' is an alias - this uses up to 3 bytes per character. Fortunately, MySQL has since supported 'utf8mb4' since version v5.7 (MariaDB 10.2).

Since MariaDB 10.6 has swapped the alias (utf8 -> utf8mb3) however this is _configurable_ and will eventually be a permanent change to alias as 'utf8mb4'.

https://mariadb.com/docs/server/release-notes/mariadb-enterprise-server-10-6/10-6-4-1/#Operational_Enhancements

It's recommended to avoid aliases and shorthands altogether. Use the full names (i.e. utf8mb3 or utf8mb4).


### Hacks

Hacks are hacks, avoid them if you can. In particular the MySQL hacks for timezone and engine substitution have been replaced with the `session` config and will be removed in v1.0.


### Examples

For MySQL Sprout:

```php
return [
    'type' => 'mysql',
    'host' => 'localhost',
    'user' => 'project-user',
    'pass' => 'super-secret-password'
    'database' => 'project-db',
    'prefix' => 'sprout_',
    'session' => [
        'sql_mode' => 'NO_ENGINE_SUBSTITUTION',
        'time_zone' => 'Adelaide/Australia',
    ],
];
```


For SQLite:

```php
return [
    'type' => 'sqlite',
    'dsn' => '/path/to/file',
    'hacks' => [
        'sqlite_functions',
    ],
]
```
