<?php

namespace karmabunny\pdb\Drivers;

use Exception;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Pdb;
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
        $q = 'SELECT name FROM sqlite_master';
        $res = $this->query($q, [], 'col');
        $pattern = '/^' . preg_quote($this->config->prefix, '/') . '/';

        foreach ($res as &$row) {
            $row = preg_replace($pattern, '', $row);
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
                name,
                type,
                notnull,
                pk,
                dflt_value
            FROM pragma_table_info(?)
        ";

        $params = [$this->config->prefix . $table];
        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $rows[] = new PdbColumn([
                'column_name' => $row[0],
                'column_type' => $row[1],
                'is_nullable' => ! (bool) $row[2],
                'is_primary' => (bool) $row[3],
                'column_default' => $row[4],
                'extra' => '',
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function indexList(string $table)
    {
        return [];
    }


    /** @inheritdoc */
    public function foreignKeyList(string $table)
    {
        return [];
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
    public function extractEnumArr(string $table, string $column)
    {
        // Enums kinda exist.
        // https://stackoverflow.com/a/17203007/7694753

        throw new Exception('Not implemented: ' . __METHOD__);
    }

}
