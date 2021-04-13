<?php

namespace karmabunny\pdb\Drivers;

use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbHelpers;
use PDO;

/**
 *
 * @package karmabunny\pdb
 */
class PdbMysql extends Pdb
{

    /** @inheritdoc */
    public function listTables()
    {
        $q = "SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
        ";

        $params = [$this->config->database];
        $res = $this->query($q, $params, 'col');

        foreach ($res as &$row) {
            $row = $this->stripPrefix($row);
        }
        unset($row);

        return $res;
    }


    /** @inheritdoc */
    public function tableExists(string $table)
    {
        $q = "SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'count');
        return (bool) $res;
    }


    /** @inheritdoc */
    public function fieldList(string $table)
    {
        $q = "SELECT
                COLUMN_NAME,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_KEY,
                COLUMN_DEFAULT,
                EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->pdb->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $rows[] = new PdbColumn([
                'column_name' => $row[0],
                'column_type' => $row[1],
                'is_nullable' => (bool) $row[2],
                'is_primary' => $row[3] == 'PRI',
                'column_default' => $row[4],
                'extra' => $row[5],
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function indexList(string $table)
    {
        $q = "SELECT
                INDEX_NAME,
                NON_UNIQUE,
                COLUMN_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->pdb->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $key = $row[0];

            // Ohhhhh I want nullish assignment.
            $item = $rows[$key] ?? new PdbIndex([
                'name' => $row[0],
                'type' => $row[1],
            ]);

            $item->columns[] = $row[2];
            $rows[$key] = $item;
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function getForeignKeys(string $table)
    {
        $q = "SELECT
                K.CONSTRAINT_NAME,
                K.TABLE_NAME,
                K.COLUMN_NAME,
                K.REFERENCED_TABLE_NAME,
                K.REFERENCED_COLUMN_NAME,
                C.UPDATE_RULE,
                C.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS K
            INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS C
                ON K.CONSTRAINT_NAME = C.CONSTRAINT_NAME
                AND K.TABLE_SCHEMA = C.CONSTRAINT_SCHEMA
            WHERE K.TABLE_SCHEMA = ?
                AND K.CONSTRAINT_NAME != ''
                AND K.TABLE_NAME = ?
            ORDER BY
                K.TABLE_NAME,
                K.COLUMN_NAME
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');

        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => $row[0],
                'source_table' => $this->stripPrefix($row[1]),
                'source_column' => $row[2],
                'target_table' => $this->stripPrefix($row[3]),
                'target_column' => $row[4],
                'update_rule' => $row[4],
                'delete_rule' => $row[5],
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function getDependentKeys(string $table)
    {
        $q = "SELECT
                K.CONSTRAINT_NAME,
                K.TABLE_NAME,
                K.COLUMN_NAME,
                K.REFERENCED_TABLE_NAME,
                K.REFERENCED_COLUMN_NAME,
                C.UPDATE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS K
            INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS C
                ON K.CONSTRAINT_NAME = C.CONSTRAINT_NAME
                AND K.TABLE_SCHEMA = C.CONSTRAINT_SCHEMA
                AND C.DELETE_RULE = 'CASCADE'
            WHERE K.TABLE_SCHEMA = ?
                AND K.CONSTRAINT_NAME != ''
                AND K.REFERENCED_TABLE_NAME = ?
                AND K.REFERENCED_COLUMN_NAME = 'id'
            ORDER BY
                K.TABLE_NAME,
                K.COLUMN_NAME
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => $row[0],
                'source_table' => $this->stripPrefix($row[1]),
                'source_column' => $row[2],
                'target_table' => $this->stripPrefix($row[3]),
                'target_column' => $row[4],
                'update_rule' => $row[4],
                'delete_rule' => $row[5],
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function extractEnumArr(string $table, string $column)
    {
        Pdb::validateIdentifier($table);
        Pdb::validateIdentifier($column);

        $q = "SHOW COLUMNS FROM ~{$table} LIKE ?";
        $res = $this->query($q, [$column], 'row');

        $arr = PdbHelpers::convertEnumArr($res['Type']);
        return array_combine($arr, $arr);
    }

}