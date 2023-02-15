
# Query builder

Pdb exposes multiple ways to safely and efficiently query data.


## Build Clause

Many of these query tools utilise the 'conditions builder' aka 'build clause'. This is documented in the [Condition Builder](./build-clause.md) guide. Any of these query methods that accept a `$conditions` array will be using this builder.


## Basic Access

> `Pdb::query($sql, $params, $type): mixed`

This is effectively a shorthand for `prepare()` + `execute()`.

Make a query against the database. The SQL will be processed for table prefixes using the `~table` identifier. This produces an escaped identifier, as appropriate for the database type.

For MySQL:

```sql
SELECT * FROM ~table
-- becomes
SELECT * FROM `pdb_table`
```

Parameters can be bound to the query using the numeric `?` or `:key` identifiers. You cannot combine these in the same query.

```php
$sql = "SELECT *
    FROM ~table
    WHERE id = :id
    AND name = :name
";
$params = [
    'id' => 123,
    'name' => 'abc',
];
$rows = $pdb->query($sql, $params, 'arr');
```

Return types are documented in the [Formatting](./formatting.md) guide.


> `Pdb::prepare($sql, $params): PDOStatement`

This is one-half of the `query()` method. It create a prepared query and binds the parameters without executing against the database. Pipe this into the `execute()` method to send it to the database.


> `Pdb::execute($statement, $type): mixed`

This is the second-half of the `query()` method. executes a prepared statement against the database and processes the results.


## Shorthand helpers

> `Pdb::get($table, $id): array`

This fetches a table row by ID. If the row doesn't exit it throws a `RowMissingException`.


> `Pdb::lookup($table, $conditions, $order, $name): array`

This fetches a mapping of `[IDs => names]` within the given conditions.

To get all records of a table, pass an empty 'conditions' array.


> `Pdb::recordExists($table, $conditions): bool`

This asserts that a record exists within the given conditions.


## Schema introspection

- `Pdb::listTables()`
- `Pdb::tableExists()`
- `Pdb::indexList()`
- `Pdb::fieldList()`
- `Pdb::getForeignKeys()`
- `Pdb::getDependentKeys()`
- `Pdb::getTableAttributes()`
- `Pdb::extractEnumArr()`

Each of these are documented within the class reference. Their behaviour is database specific. As such, some are not-yet implemented or straight up impossible to achieve. Such as `extractEnumArr()` for anything non-MySQL based.


## Query builder


The query builder is a powerful tool that writes SQL queries using a builder object pattern. This lets one easily manipulate a query programmatically without having to worry about SQL syntax correctness. The only syntax to worry about is PHP.

To create a PdbQuery:

```php
// The constructor:
$query = new PdbQuery($pdb);

// The helper:
$query = $pdb->find('table');

// The model object:
$query = MyPdbModel::find();
```

PdbModels are covered in the [Modelling](./modelling.md) guide.


### Anatomy of a query

Once query object is initialised, one can manipulate the query with the various 'filter' or 'modifier' methods.

After creating all the bits of a query use 'terminator' method to execute the query and retrieve the results.


### Filters

Filters are the bread-and-butter for manipulating a query. These should typically match the relevant SQL keywords.

All filters self-return, meaning they can be chained together to form a concise query statement. Terminators, by nature, terminate the query chain because they instead return the query result.

The builder pattern doesn't specify that one must use these in any particular order. If one feels so inclined `->from()->where()->select()` is perfectly valid.


> `select(...$fields)`

- aliases

- nested arrays

- escapes, functions, wildcards

Calling this method a multiples times will replace the previous selects.


> `andSelect(...$fields)`

This appends to an existing list of selects instead of replacing it.


> `from($table, [$alias])`

Choose a table to select from.

- aliases


> `alias($name)`

Overwrite the alias of the FROM clause.


> `leftJoin($table, $conditions)`

- aliases

- nested conditions

- string conditions


> `innerJoin($table, $conditions)`

- aliases

- nested conditions

- string conditions


> `where($conditions)`

Create a clause using the [Condition Builder Syntax](./build-clause.md).

Calling this method a multiples times will replace the previous clauses.


> `andWhere($conditions)`

This appends to an existing clause with an `'AND'`.


> `orWhere($conditions)`

This appends to an existing clause with an `'OR'`.


> `groupBy(...$fields)`

Set the grouping fields.


> `orderBy(...$fields)`

- direction
- aliases?
- umm. alt syntax rubbish?


> `limit($limit)`

Limit the results from a query.

Specify `limit(null)` to clear an existing limit.


> `offset($limit)`

Set the offset of a query.

Specify `offset(null)` to clear an existing offset.


> `find($table, $conditions)`

This is a shorthand that combines the filters `->from($table)->where($conditions)`.



### Modifiers

These are helpers that inform any transformations to perform after receiving the data.


> `as($class)`

Convert results into a class object. These classes must accept an array in their constructor.

Valid terminators:

- `one(): object`
- `all(): object[]`
- `keyed(): object[]`
- `iterator(): iterable<object>`
- `batch($size): iterable<object[]>`


> `cache($key, $ttl)`

Control caching rules for the query. Caching layers are documented in the [Caching](./caching.md) guide.

Possible forms:

- `cache(true)` - use global TTL
- `cache(10)` - cache for 10 seconds
- `cache(false)` - disable cache
- `cache('key', true)` - cache with 'key' with the global TTL
- `cache('key', 10)` - cache with 'key' for 10s
- `cache('key', false)` - disable cache

Each Pdb instance can configure it's caching TTL config.


> `indexBy($field)`

TODO


> `throw([$bool])`

TODO



### Terminator methods

> `build(): [string, array]`

This is the core terminator. The result is a pair - a parameterised SQL query string and an array of values to bind to it. These can be manually fed into `Pdb::query()` if one feels so inclined.

```php
[$sql, $params] = $pdb->find('table')
    ->where(['id' => 123])
    ->limit(1)
    ->select('id', 'name')
    ->build();

$row = $pdb->query($sql, $params, 'row?');
```


> `execute(string $type): mixed`

This executes and applies the default [formatter configuration](./formatting.md) to the query. The result will match that of the documentation.

For example:

```php
$ids = $pdb->find('table')
    ->select('id')
    ->limit(10)
    ->execute('col');
```

This is identical to the `->column()` terminator. Most, if not all, default return types will be covered by one of the existing terminators.


> `pdo(): PDOStatement`

Return the raw `PDOStatement` object. The caller is responsible for closing the cursor once they're finished using the result.


> `value($field): string`


> `one($throw): array|object`

can build classes


> `all(): array`

can build classes


> `map($key, $value): array`


> `keyed($key): array`

can build classes


> `column(): array`


> `count(): int`


> `exists(): bool`


> `iterator(): iterable`

can build classes


> `batch($size): iterable<array>`

can build classes


### Storing Queries

Query configurations can be stored as an array (or JSON blob) for later use or transport.

To build a query from an array, use the second constructor argument:

```php
// Serialize it.
$config = $oldQuery->toArray();

// Store it, send it, compress it. Whatever.
$serialized = serialize($config);
$json = json_encode($config);

// Hydrate it.
$newQuery = new PdbQuery($pdb, $config);
```

This assumes that all bound values are JSON serialisable or otherwise.


### Extending PdbQuery

PdbQuery doesn't stop here - there's nothing stopping you from combining your own filters or introducing additional modification during the `build()` stage by extending the `PdbQuery` class and adding your flavours.

One example can be seen in the `PdbModelQuery` class that introduces inflection and a `deleted()` filter.
