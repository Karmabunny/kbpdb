
## PDB

A PDO wrapper for fun and profit.

This is ripped directly from Sprout 3 with a lot of modification. There's a set of (in-progress) compat classes to enable one to 'backport' the updates here into a Sprout site.

General improvements are things like:
- consistent db-agnostic column escapes
- query builder
- models
- improved dbsync
- migration exports
- adapter architecture for other DBs (postgres, sqlite, mssql)
- more tests
- data binding interface


## Install

Add as a dependency:

```sh
composer require karmabunny/pdb
```


## Examples

Usage is documented in the `docs/` folder.


## Contributing

Please see the TODO and CONTRIBUTING documents.
