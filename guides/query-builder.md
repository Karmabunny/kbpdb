
# Query builder


Query elements:

- `select(...$fields)`
- `andSelect(...$fields)`
- `from($table)`
- `leftJoin($table, $conditions, $combine)`
- `innerJoin($table, $conditions, $combine)`
- `where($conditions, $combine)`
- `andWhere($conditions, $combine)`
- `orWhere($conditions, $combine)`
- `groupBy($field)`
- `orderBy(...$fields)`
- `limit($limit)`
- `offset($limit)`

Shorthands:

- `find($table, $conditions)`

Modifiers:

- `as($class)`
- `cache($ttl)`

Terminator methods:

- `build(): [string, array]`
- `value($field): string`
- `one($throw): array|object`
- `all(): array`
- `map($key, $value): array`
- `keyed($key): array`
- `column(): array`
- `count(): int`
- `exists(): bool`
- `iterator(): iterable`
- `batch($size): iterable<array>`
- `pdo(): PDO`
- `execute(): mixed`

Class builders:

- `one(): object`
- `all(): object[]`
- `keyed(): [key => object]`
- `iterator(): iterable<object>`
- `batch($size): iterable<object[]>`


## Some examples

```php
<?php

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbQuery;

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

// Alternatives
$res = $pdb->select('id', 'name')->from('clubs')->all(100);
$res = $pdb->find('clubs', ['status' => 'active'])->keyed('id');
$res = $pdb->count('clubs', ['status' => 'active']);

```
