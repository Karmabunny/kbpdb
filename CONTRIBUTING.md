
# PDB Contributing

PDB is a open project, however much of the work is performed at the pleasure of Karmabunny for it's own purposes.

That said - please feel free to submit issues, PRs or ideas. We can't guarantee that we'll accept all contributions so please do create a ticket before committing to writing a PR.

If you need ideas about what you can contribute, have a look at the TODOs document or the list of missing tests in the 'Writing Tests' section.


## Getting started

### 1. Requirements

To get started developing PDB, one needs an appropriate environment:

- PHP 7.x
- Composer 2.x
- MySQL 5.7+ / MariaDB 10.3+


### 2. Pull and install the latest revision

```
git clone git@github.com:Karmabunny/kbpdb
composer install
```


### 3. Configure MySQL

Although PDB aims to be DB-independent, much of it was written with MySQL so this is the only _fully_ supported backend. As such all tests must pass with MySQL.

You'll need a local database for the tests to connect to. This can be a machine installed or docker environment. Whatever suits you.

Configuration:

 - `'type' => 'mysql'`
 - `'host' => '127.0.0.1'`
 - `'user' => 'kbpdb'`
 - `'pass' => 'password'`
 - `'database' => 'kbpdb'`


```sql
CREATE DATABASE IF NOT EXISTS kbpdb;
CREATE USER IF NOT EXISTS kbpdb@'%' IDENTIFIED BY 'password';
GRANT ALL ON kbpdb.* TO kbpdb@'%';
```


## Roadmap

Note that this library has not yet reached `v1.0` 'stability'. However real projects are already using this library so it's fair to say we shouldn't be breaking things if we don't have to.

Key things holding back `v1.0`:
- test coverage
- concrete definition of the adapter (driver) interface
- better support across other adapters
- per-adpater type normalisation
- relational models (there's caution about the possibly breaking complexity this could introduce)


There's ambiguity about how some interfaces behave and big mistakes still being made. Once these key points are resolved and the TODOs document is reviewed then we'll likely see the `v1.0` release.



## Writing tests

Perhaps the tests are a little messy, but they're reasonably structured that we can get stuff done without hassle. Feel free to suggest a better layout.

```sh
# Execute the test suite using this
composer test

# Filter your tests
composer test -- --filter cache
```


### Some rules

1. The test suite should always be able to run without a web server or database. Please properly skip relevant tests using the PHPUnit utilities.

2. New contributions won't be accepted without tests. More importantly, if they're modifying functionality that already _lacks_ tests then this will need to be submitted as two PRs as a 'before and after' set. Of course this is all within discretion of the maintainer. We're not jumping hoops if we don't have to.

3. Do not require code edits to run a test. The testing process is literally: pull-install-test.


### Some tests you can help write

- Query building
- Various model scenarios
- Parser and Sync
- Caching
- Postgres


### Test types

#### 1. Database independent functionality

These are features that don't need a backend to complete tests. Things like caching, conditions, query builders, parsers, etc.

As always, there are exceptions. For example query test that interface with return-types. These should be run against SQLite if possible.


#### 2. Database agnostic tests

Much functionality needs to perform the same regardless of the backing database. These tests should written in the `BasePdbCase` test, which is then run against each available database connection.


#### 3. Legacy tests

The `StaticPdbTest` is ripped directly from the Sprout project.

This is the contract made with Sprout to say: 'Yes, we still behave like you have always expected of us'. _Do not break these tests_.
