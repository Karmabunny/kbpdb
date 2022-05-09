#!/usr/bin/env bash
set -e

FIND='(Sprout\\Exceptions\\)(Query|TransactionRecursion|ConstraintQuery|RowMissing)Exception'
REPLACE='karmabunny\\pdb\\Exceptions\\\2Exception'

grep -rlE "$FIND" "$1" | grep -v .git | while read line; do
    echo "$line"
    sed -i.bak -E "s/$FIND/$REPLACE/" $line
    rm $line.bak
done

echo "Done"
echo
