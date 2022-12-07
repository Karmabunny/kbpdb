# Getting Started

This a Composer library that aims to simplify integration with the PHP PDO library.


## Key features

- Simplified query binding
- Declarative migrations
- Query builder
- Nested transactions
- Modelling

Other good wins:

- Table prefixing
- Adapters for Mysql, Postgres, Sqlite
- Caching layers
- Inflectors
- Logging and profiling hooks
- Descriptive exceptions


## Installing

This assumes you're using Composer in your project. Otherwise you're on your own.

```sh
composer require karmabunny/pdb
```

Instructions for integrating with Sprout can be found [here](./static-pdb.md).


## First steps

1. Have a read of the [Config](./config.md) section to get something ready.

2. Create a migration using the [PdbSync](./pdbsync.md) features.

3. Then cover some [Basics](./basics.md) about using the Pdb object.

4. Finally, explore some of more advanced features:

   - [Query Builder](./query-builder.md)
   - [Modelling](./modelling.md)
   - [Caching](./caching.md)
   - [Transactions](./transactions.md) - incomplete, to be merged
   - [Return Formats](./formatting.md)
   - [Data Binding](./data-binding.md)

