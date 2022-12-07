
# Data binding + Formatters

Pdb provides a few ways to customise the data marshalling experience. There are two similar interfaces for this, each with unique use-cases. These essentially behave the the same but but within different scopes.


## PdbDataBinderInterface

Pdb exposes shorthand methods for doing safe insertion of data, that is, `insert()/update()/upsert()`.
By default these will insert the scalar value with a `SET column = ?` binding. The value is parameterized (as always) - for performance and safety.

This interface lets one customise the value before binding as well as the binding SQL snippet.

Usage:

```php
$pdb->insert('table', [
   'data' => new MyDataBinder($value),
]);
```

**Important!** If a data binder is used in a regular query, this `getBindValue()` is still executed:

```php
$pdb->query('SELECT * FROM ~table where col = ?', [
    new MyDataBinder($value),
], 'row');
```


### Built-in Bindings

Pdb provides some utility binders for convenience.


#### ConcatDataBinder

This produces a `SET column = CONCAT(column, ?)` binding.


#### JsonLinesDataBinder

This will both encode the value as a JSON object and produces a binding like:

```swl
SET column = CONCAT(column, ?, '\n')
```

The binder class also provides a parser for convenience:

```php
$iterable = JsonLinesDataBinder::parseJsonLines($blob);
```

## PdbDataFormatterInterface

Formatters can also modify the binding SQL for an object, as well as format the value before binding.

However, there are limitations:

- only executes on matching objects (and child objects)
- executes after binders

Usage:

```php
$pdb->config->formatters[MyObject::class] = new MyFormatter();

$pdb->insert([
    'date_added' => new DateTime(),
]);
```

### Configuring a formatter

The formatters config is an array of 'configurable' types. That is, you can provide one of an object instance, class name, or array like `[ class => [config] ]`.


### Built-in Formatters


#### CallableFormatter

This wraps any inline function formatters in the `formatters` config.


#### DateTimeFormatter

This formats anything that inherits `DateTimeInterface`. Formats can be configured with the formatter. For example:


```php
// Default formatter (Y-m-d H:i:s)
$pdb->config->formatters[DateTimeInterface::class] = DateTimeFormatter::class;

// Configured formatter
$pdb->config->formatters[DateTimeInterface::class] = new DateTimeFormatter('Y-m-d');
```
