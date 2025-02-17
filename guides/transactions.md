
# Transactions

PDB supports both traditional (recursion-free) transactions as well as nested transactions through the use of savepoints. Along with a few other options to make life easier (or harder).

Transaction modes can be configured using the `transaction_mode` config:

| Mode                 | Description                               | Note                                              |
|----------------------|-------------------------------------------|---------------------------------------------------|
| TX_STRICT_COMMIT     | Require active TX on commit               | def: true                                         |
| TX_STRICT_ROLLBACK   | Require active TX on rollback             | def: true                                         |
| TX_ENABLE_NESTED     | Permit nested transactions via savepoints | def: false. This eliminates recursion exceptions  |
| TX_FORCE_COMMIT_KEYS | Require a TX key on commit()              | def: false. This may break existing (Sprout) code |


#### Strict commit / rollback

A user is not permitted to call `commit()` or `rollback()` without an active transaction. This is the existing behaviour, but now toggle-able. By default exceptions for both commit + rollback are enabled for backwards compatibility.

It's good to have this enabled for `commit()` so one doesn't think that data is being saved when it is not.

For `rollback()` however, it's beneficial to simply slap this around the place without concern if a transaction is active or not. Like a `finally {}` or something - to say that the default is "undo changes unless I've committed".


#### Commit keys

This option requires that `commit()` is provided a key returned by `savepoint()` or `transact()`. This forces the user to retain that key through their transaction process - thus hoping to enforce better practices.

Conceptually:
- This keeps the 'transaction' as a present object for the programmer and reminds them that they must resolve it before finishing.
- It helps keep the transaction within the same lexical scopes. Which can only be a good thing. I think.


#### Nesting

With this `transact()` doubles as a `savepoint()` if there's a base transaction already present. 'Transaction recursion' errors are no longer present. Calling `commit()` or `rollback()` with the 'savepoint key' will transact on only that savepoint. Without a key it will transact the whole transaction.

For example:

```php
$tx = $pdb->transact();
try {
    $save1 = $pdb->transact();
    // do pdb things

    $save2 = $pdb->transact();
    // do more things

    // save1 _and_ save2 are released.
    $save1->commit();

    // Commit the transaction.
    $tx->commit();
}
finally {
    // Assuming non-strict rollbacks.
    $tx->rollback();
}
```


#### Transaction callback!

Goes like this:

```php
$pdb->withTransaction(function() use ($pdb) {
    $pdb->query("etc etc");
});
```

_That's it._

This will start a transaction, run the callback and commit it or rollback if an error is thrown. Just magic.
