#!/usr/bin/env bash
set -e

FIND='use (Sprout\\Exceptions\\)(QueryException|TransactionRecursionException|ConstraintQueryException|RowMissingException);'
REPLACE='use karmabunny\\pdb\\Exceptions\\\2;'

grep -rlE "$FIND" "$1" | while read line; do
    echo "$line"
    sed -i.bak -E "s/$FIND/$REPLACE/" $line
    rm $line.bak
done

echo "Done"
echo
echo
cat - <<PDB
<?php
namespace Sprout\Helpers;

class Pdb extends karmabunny\pdb\Compat\Pdb {}
PDB

echo
echo
cat - <<DBSYNC
<?php
namespace Sprout\Helpers;

class DatabaseSync extends karmabunny\pdb\Compat\DatabaseSync
{
    /**
     * Load the db_struct.xml from core and from all modules
     */
    public function loadStandardXmlFiles()
    {
        $this->loadXml(APPPATH . 'db_struct.xml');

        $module_paths = Register::getModuleDirs();
        foreach ($module_paths as $path) {
            $path .= '/db_struct.xml';
            if (file_exists($path)) {
                $this->loadXml($path);
            }
        }
    }

}
DBSYNC
