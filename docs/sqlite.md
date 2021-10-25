
# Support for other Databases (Sqlite, Postgres)

Most people don't see value is this and perhaps they're right.

But the value I see is this:

- To re-affirm the abstractions that we have created.
- A spring-cleaning for years of workaround and hacks.
- To permit exploration of new applications (maybe in new environments).


## Goals + TODOs

### Remove MySQL specific queries from application code (Sprout 3)

These should be wrapped up in PDB helpers. If not portable, then behind an adapter check and restricted from the user.

Key offenders:
- AdminController
- DbToolsController
- ManagedAdminController
- Export
- ExportDBMS_MySQL
- ExportDBMS_SQLite
- File


### Clean out 'no_engine_substitution' from core Sprout3

Defaults should be respected. Weak constraints lead to dirty data.


### Figure out FKs for Sqlite

Either commit to:
1. hacky (genuinely dangerous) editing schemas, or
2. drop column, create column with FK

Instead of dropping the column, we could preserve data by renaming it to something like `old_<colname>_xyz`.

Then perhaps:
```sql
UPDATE OR IGNORE target_table AS t1
SET new_col = (
    SELECT old_col
    FROM target_table AS t2
    WHERE t1.id = t2.id
);
```


### Proper UPSERT support

This also applies to MySQL. The current 3x query solution is pretty gross.


### Move type normalisation to adapters

Currently PdbHelpers::normalizeType() is MySQL specific.


### Store enum values (PdbColumn) in an array

Normalise these into distinct values. Maybe somehow deprecate the string style.

This will help support for other backends (Sqlite uses CHECK).

Perhaps enum/set lookups could be done from a serialized schema instead of the database.


### Special hacks for Sprout 3

There are a lot of assumed functions, particular for date formatting/mangling.

But lucky us, sqlite (and also baked into PDO) lets us create custom functions that call back into PHP. So the sky is the limit.

I would also suggest that we create a custom collation so we don't lose our lovely unicode comparisons.
