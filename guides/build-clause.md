
# Condition Builder


## Build Clause

The conditions builder can also be accessed by the `Pdb::buildClause()` method. This is the traditional way to build a conditions array before binding parameters to a query.

For example:

```php
$conditions = [];
$conditions[] = [ 'name', 'CONTAINS', 'abc' ];
$conditions[] = [ 'date_added', '>=', '2020-01-01' ];

$params = [];
$clause = $pdb->buildClause($conditions, $params);

$q = "SELECT *
    FROM ~table
    WHERE {$clause}
    ORDER BY name
";

$rows = $pdb->query($q, $params, 'arr');
```

The executed query looks like:

```sql
SELECT *
FROM `pdb_table`
WHERE `name` LIKE CONCAT('%', ?, '%'),
AND `date_added` >= ?
ORDER BY name
```


## Other appearances

The conditions builder is used extensively through Pdb query helpers and the `PdbQuery` builder interface.

Some examples:

- `Pdb::lookup($table, $conditions)`
- `Pdb::recordExists($table, $conditions)`
- `Pdb::update($table, $data, $conditions)`
- `Pdb::find($table, $conditions)`
- `PdbQuery::where($conditions)`
- `PdbQuery::having($conditions)`
- `PdbQuery::leftJoin($table, $conditions)`
- `PdbQuery::innerJoin($table, $conditions)`



## Operators

### Simple operators

- Equality: `'=' '!=' '<>'`

- Comparison: `'>' '>=' '<' '<='`

- NULL operators: `'IS' 'IS NOT'`


### LIKE operators

- `CONTAINS` produces `LIKE CONCAT('%', ?, '%')`
- `BEGINS` produces `LIKE CONCAT(?, '%')`
- `ENDS` produces `LIKE CONCAT('%', ?)`


### Other operators

> `'BETWEEN'`

This accepts an array with two values and produces:

```sql
`column` BETWEEN ? AND ?
```

> `'IN'` and `'NOT IN'`

This accepts any size array and produces:

```sql
`column` NOT IN (?, ?, ?, ?)
```

The number of placeholders will be equal to the array size.

> `'IN SET'`

This is a MySQL-only feature for 'set' column types.

```sql
FIND_IN_SET(?, `column`)
```


## Condition Forms

There is more than one way to write a condition - just to keep you on your toes. Realistically though there are legitimate cases for each form. Some are brief a easy to read and others are required in order to make complex queries.


### Traditional Form

> `[column, operator, value]`

Multiple conditions can be joined together by wrapping them in an array to form a combined `'AND'` condition.

```php
[
    ['column1', '=', 'abc'],
    ['column2', '>=', 100],
]
```

### String Form

> `'table.column = other.column'`

Avoid using this form for anything other than table joins. This exposes a can-of-worms for SQL injection if used poorly.

In future it may be possible to use parameterized placeholders in these conditions like `?` and `:value`.


### Associated Form

> `[column => value]`

This can only express equality conditions such as `=` or `IS` for 'null' types.


Multiple conditions can be expressed in the same array body to form an `'AND'` combined condition.

```php
[
    'column1' => 123,
    'column2' => null,
]
```

Produces:

```sql
column1 = ? AND column2 IS NULL
```

Care should be taken when combining this with other forms. In this scenario it's best to wrap these in individual arrays:

```php
[
    // Traditional form
    ['column1', 'CONTAINS', 'abc'],
    // Associated form
    ['uid' => '123-456'],
]
```


### Modified Associated Form

> `[operator, column => value]`

This is much like the associated form but lets one change the operator. This is technically as powerful as the traditional form.


### Negated Conditions

This is an extension of the 'Nested Conditions' feature described below.

Negative conditions can be created by wrapping a typical condition in a `'NOT'` operator.

```php
// Wrap an associated form.
[ 'NOT' => ['id' => 123] ]

// Wrap a traditional form.
[ 'NOT' => ['id', '=', 123] ]

// This also works as a shorthand for the modified associated form.
[ 'NOT', 'id' => 123 ]
```

Note that the builder is not smart enough to convert `'NOT id = 123'` into `'id != 123'`. You get what you ask for.


## Nested conditions

Nested conditions can help one to create complex queries without having to splice multiple strings together. This is particularly powerful when using the `PdbQuery` builder.

When building multiple conditions, often the builder will provide a 'combine' parameter to control the root level combine operator. This is by default the `'AND'` operator. However one might find that using this nested feature is more accessible - this is ok, you do you.

To create nested conditions, wrap them in a `AND/OR/XOR` operator. Go as deep and wide as one likes.



```php
[
    'OR' => [
        ['name' => 'abc'],
        [ 'AND' => [
            ['postcode', 'IN', [5000, 6000, 7000]],
            ['country' => 'AUD'],
        ],
    ],
]
```

This produces a clause:

```sql
(
    `name` = ?
    OR (
        `postcode` IN (?, ?, ?)
        AND `country` = ?
    )
)
```
