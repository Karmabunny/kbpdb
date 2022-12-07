
# Return Formatting

Pdb has extended features for formatting data as it is retrieved from the database.

This includes class creation, caching, iteration, exceptions and the lot.


## ReturnTypeInterface

This is the base interface for all return type configs, used in these methods:

- `query(string, array, PdbReturnTypeInterface)`
- `execute(PDOStatement, array, PdbReturnTypeInterface)`



## Default Config

The default return config is used throughout Pdb wherever he return config is not a `PdbReturnTypeInterface` and instead an array or string.


### Array config

- `type`      (string)      - 'pdo' or a format type (detailed below)
- `class`     (string)      - a class name to wrap results (for 'row', 'arr', 'map-arr')
- `cache_ttl` (int|bool)    - cache expiry in seconds, true to use global config, false to disable
- `cache_key` (string)      - an override cache key, providing a user with invalidation powers
- `map_key`   (string|null) - a column for 'map-arr' or `null` for the first column
- `throw`     (bool)        - throw an exception if the row is missing (default true)


### Shorthand config

The default formatter accepts a shorthand 'type' string. When building queries, one can simply enter the type name and the result will be formatted as such.

For example:

```php
$row = $pdb->query("SELECT * FROM ~table LIMIT 1", [], 'row');
```


### Return types

 - `pdb`      this performs no formatting, the raw `PDOStatement` object is returned instead. One must close this after use.

 - `null`     just a null.

 - `count`    the number of rows returned, or if the query is a
              `SELECT COUNT()` this will behave like `'val'`

 - `arr`      An array of rows, where each row is an associative array.
              Use only for very small result sets, e.g. <= 20 rows.

 - `arr-num`  An array of rows, where each row is a numeric array.
              Use only for very small result sets, e.g. <= 20 rows.

 - `row`      A single row, as an associative array
              Given an argument `'row:null'` or the shorthand `'row?'` this
              will return `'null'` if the row is empty.
              Otherwise this will throw a RowMissingException.

 - `row-num`  A single row, as a numeric array
              Given an argument `'row-num:null'` or the shorthand `'row?'`
              this will return` 'null'` if the row is empty.
              Otherwise this will throw a RowMissingException.

 - `map`      An array of `identifier => value` pairs, where the
              identifier is the first column in the result set, and the
              value is the second

 - `map-arr`  An array of `identifier => value` pairs, where the
              identifier is the first column in the result set, and the
              value an associative array of `name => value` pairs
              (if there are multiple subsequent columns)
              Optionally, one can provide an 'column' argument with the
              type string in the form: `'map-arr:column'`.

 - `val`      A single value (i.e. the value of the first column of the
              first row)
              Given an argument `'val:null'` or the shorthand 'val?' this
              will return `'null'` if the result is empty.
              Otherwise this will throw a RowMissingException.

 - `col`      All values from the first column, as a numeric array.
              DO NOT USE with boolean columns; see note at
              http://php.net/manual/en/pdostatement.fetchcolumn.php

