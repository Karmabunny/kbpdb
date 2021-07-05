<?php

namespace karmabunny\pdb\Drivers;

use Exception;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbHelpers;
use PDO;

/**
 *
 * @package karmabunny\pdb
 */
class PdbSqlite extends Pdb
{

    /** @inheritdoc */
    public function getPermissions()
    {
        // SQLite doesn't have users/permissions - anything goes.
        return PdbHelpers::TYPES;
    }


    /** @inheritdoc */
    public function listTables()
    {
        $q = "SELECT name
            FROM sqlite_master
            WHERE type = 'table'
        ";
        $res = $this->query($q, [], 'col');

        foreach ($res as &$row) {
            $row = $this->stripPrefix($row);
        }
        unset($row);

        return $res;
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
            $rows[$key] = new PdbColumn([
                'name' => $row[0],
                'type' => $row[1],
                'is_nullable' => ! (bool) $row[2],
                'is_primary' => (bool) $row[3],
                'default' => $row[4],
                // I guess??
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
        throw new Exception('Not implemented: ' . __METHOD__);
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
        // Enums kinda exist.
        // https://stackoverflow.com/a/17203007/7694753

        throw new Exception('Not implemented: ' . __METHOD__);
    }

}
