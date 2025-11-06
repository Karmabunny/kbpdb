<?php

namespace karmabunny\pdb\Drivers;

use Exception;
use karmabunny\pdb\Extra\SqliteFunctions;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
use karmabunny\pdb\PdbHelpers;
use PDO;

/**
 *
 * @package karmabunny\pdb
 */
class PdbSqlite extends Pdb
{

    static $FUNCTIONS = [
        'DATE_FORMAT' => [SqliteFunctions::class, 'dateFormat'],
    ];


    /** @inheritdoc */
    protected static function afterConnect(PDO $pdo, PdbConfig $config, array $options)
    {
        $hacks = $config->getHacks();
        $fns = $hacks[PdbConfig::HACK_SQLITE_FUNCTIONS] ?? [];
        $fns = array_intersect_key(static::$FUNCTIONS, $fns);

        foreach ($fns as $name => $fn) {
            // @phpstan-ignore-next-line: it definitely supports 4 args.
            $pdo->sqliteCreateFunction($name, $fn, -1, PDO::SQLITE_DETERMINISTIC);
        }

        parent::afterConnect($pdo, $config, $options);
    }


    /** @inheritdoc */
    public function getPermissions()
    {
        // SQLite doesn't have users/permissions - anything goes.
        return PdbHelpers::TYPES;
    }


    /** @inheritdoc */
    public function getTableNames(string $filter = '*', bool $strip = true)
    {
        $q = "SELECT name
            FROM sqlite_master
            WHERE type = 'table'
            AND name NOT LIKE 'sqlite_%'
        ";
        $res = $this->query($q, [], 'col');

        if ($filter === '*') {
            $filter = $this->config->prefix;
        }

        $tables = [];
        $length = strlen($filter);

        foreach ($res as $row) {
            if ($filter and strpos($row, $filter) !== 0) continue;

            if ($strip) {
                if ($length) {
                    $tables[] = substr($row, $length);
                }
                else {
                    $tables[] = $this->stripPrefix($row);
                }
            }
            else {
                $tables[] = $row;
            }
        }

        return $tables;
    }


    /** @inheritdoc */
    public function tableExists(string $table)
    {
        self::validateIdentifier($table);

        $q = "SELECT 1
            FROM sqlite_master
            WHERE tbl_name = ~{$table}
            LIMIT 1
        ";

        $res = $this->query($q, [], 'count');
        return (bool) $res;
    }


    /** @inheritdoc */
    public function fieldList(string $table)
    {
        $q = "SELECT
                \"name\",
                \"type\",
                \"notnull\",
                pk,
                dflt_value
            FROM pragma_table_info(?)
        ";

        $params = [$this->config->prefix . $table];
        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $key = $row[0];

            $default = $row[4];

            // TODO something about null.

            // Convert numbers.
            if (is_numeric($default)) {
                if (stripos($row[1], 'int')) {
                    $default = (int) $default;
                } else {
                    $default = (float) $default;
                }
            }

            $rows[$key] = new PdbColumn([
                'name' => $row[0],
                'type' => $row[1],
                'is_nullable' => ! (bool) $row[2],
                'is_primary' => (bool) $row[3],
                'default' => $default,
                // I guess?? - Assuming PK implies autoinc.
                'auto_increment' => (bool) $row[3],
                'extra' => '',
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function indexList(string $table)
    {
        $table = $this->config->prefix . $table;

        $q = "SELECT * FROM pragma_index_list(?)";
        $res = $this->query($q, [$table], 'arr');

        $rows = [];

        foreach ($res as $row) {
            $key = $row['name'];

            $q = "SELECT name from pragma_index_info(?)";
            $columns = $this->query($q, [$key], 'col');

            $rows[$key] = new PdbIndex([
                'name' => $row['name'],
                'type' => $row['unique'] == 0 ? 'index' : 'unique',
                'columns' => $columns,
            ]);
        }

        return $rows;
    }


    /** @inheritdoc */
    public function getForeignKeys(string $table)
    {
        $prefix_table = $this->config->prefix . $table;

        $q = "SELECT * FROM pragma_foreign_key_list(?)";
        $res = $this->query($q, [$prefix_table], 'arr');

        $rows = [];

        foreach ($res as $row) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => "{$prefix_table}.{$row['from']}",
                'from_table' => $table,
                'from_column' => $row['from'],
                'to_table' => $row['table'],
                'to_column' => $row['to'],
                'update_rule' => $row['on_update'],
                'delete_rule' => $row['on_delete'],
            ]);
        }

        return $rows;
    }


    /** @inheritdoc */
    public function getDependentKeys(string $table)
    {
        throw new Exception('Not implemented: ' . __METHOD__);
    }


    /** @inheritdoc */
    public function getTableAttributes(string $table)
    {
        // for autoinc - https://stackoverflow.com/a/33285873/7694753
        return [];
    }


    /** @inheritdoc */
    public function extractEnumArr(string $table, string $column)
    {
        // https://stackoverflow.com/a/17203007/7694753
        $table = $this->config->prefix . $table;

        $q = "SELECT sql
            FROM sqlite_master
            WHERE type = 'table'
            AND name = ?
        ";
        $sql = $this->query($q, [$table], 'val');

        $matches = [];
        $ok = preg_match("/check\((?:\"{$column}\" in \(([^)]+)\)|instr\(([^,]+), \"{$column}\")\)/i", $sql, $matches);
        if (!$ok) return [];

        $arr = explode(',', $matches[1] ?: $matches[2]);

        foreach ($arr as &$item) {
            $item = trim($item, " '\t\n\r\0");
        }
        unset($item);

        return array_combine($arr, $arr);
    }

}
