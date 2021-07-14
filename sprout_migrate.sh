#!/usr/bin/env bash
set -e

FIND='use (Sprout\\Exceptions\\)(QueryException|TransactionRecursionException|ConstraintQueryException|RowMissingException);'
REPLACE='use karmabunny\\pdb\\Exceptions\\\2;'

grep -rlE "$FIND" "$1" | grep -v .git | while read line; do
    echo "$line"
    sed -i.bak -E "s/$FIND/$REPLACE/" $line
    rm $line.bak
done

echo "Done"
echo

PDB="$(find $1 -iname Pdb.php -exec realpath {} \; | grep -v .git | grep -v vendor)"
echo "Writing: $PDB"
cat > $PDB << 'PDB'
<?php
namespace Sprout\Helpers;

class Pdb extends \karmabunny\pdb\Compat\Pdb {}
PDB

echo "ok"
echo

DBSYNC="$(find $1 -iname DatabaseSync.php -exec realpath {} \; | grep -v .git | grep -v vendor)"
echo "Writing: $DBSYNC"
cat > $DBSYNC << 'DBSYNC'
<?php
namespace Sprout\Helpers;

class DatabaseSync extends \karmabunny\pdb\Compat\DatabaseSync
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

echo "ok"
echo
echo
echo "=== Pop this in Kohana::setup() ==="
echo

cat - << 'KOHANA'
$config = Kohana::config('database.default.connection');
$config['prefix'] = 'sprout_';
$config['character_set'] = Kohana::config('database.default.character_set');
Pdb::config($config);
KOHANA

echo
echo