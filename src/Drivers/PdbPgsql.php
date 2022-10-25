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
class PdbPgsql extends Pdb
{


    /** @inheritdoc */
    public function getPermissions()
    {
        // TODO Postgres has a slightly different concept about permissions.
        // The grants are multi-level. Where the database has 'create/drop'
        // grants and the tables have 'select/insert/update/delete' grants.

        // How to resolve this with the interface we currently have? idk.
        // Perhaps we make this more postgres-like and add a table name arg?

        return PdbHelpers::TYPES;
    }


    /** @inheritdoc */
    public function listTables()
    {
        $q = "SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
        ";

        $params = [$this->config->schema];
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
            $this->config->schema,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'count');
        return (bool) $res;
    }


    /** @inheritdoc */
    public function fieldList(string $table)
    {
        // TODO primary keys?

        $q = "SELECT
                column_name,
                is_nullable,
                column_default,
                data_type,
                character_maximum_length,
                numeric_precision,
                numeric_scale
            FROM information_schema.columns
            WHERE table_schema = ?
            AND table_name = ?
        ";

        $params = [
            $this->config->schema,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $key = $row[0];

            $autoinc = stripos($row[2], 'nextval') !== false;
            $is_nullable = $row[1] == 'YES';
            $default = $row[2];

            // Implicit + numeric null is just empty.
            if (!$default) {
                $default = null;
            }
            // Numeric are just numerics.
            else if (is_numeric($default)) {
                if (stripos($row[3], 'int')) {
                    $default = (int) $default;
                } else {
                    $default = (float) $default;
                }
            }
            // Bools are bools.
            else if (in_array($default, ['true', 'false'])) {
                $default = $default == 'true';
            }
            // Then there's a funny syntax for everything else.
            else {
                [$default] = explode('::', $default, 1);
                $default = trim($default, '\'');
            }

            $type = $row[3];

            if ($type == 'character varying') {
                $type = "varchar({$row[4]})";
            }
            else if ($type == 'character') {
                $type = "char({$row[4]})";
            }
            // integers.
            else if ($type == 'integer') {
                $type .= "({$row[5]})";
            }
            // numerics.
            else {
                $type .= "({$row[5]},{$row[6]})";
            }

            $rows[$key] = new PdbColumn([
                'name' => $row[0],
                'type' => $type,
                'is_nullable' => $is_nullable,
                // 'is_primary' => $row[3] == 'PRI',
                'default' => $default,
                'auto_increment' => $autoinc,
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function indexList(string $table)
    {
        $q = "SELECT
                indexname,
                indexdef
            FROM pg_indexes
            WHERE schemaname = ?
                AND tablename = ?
        ";

        $params = [
            $this->config->schema,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $matches = [];
            if (!preg_match('/(UNIQUE)?.+\(([^)]+)\)/', $row[1], $matches)) continue;

            [$_, $unique, $columns] = $matches;

            $columns = explode(',', $columns);
            $columns = array_map('trim', $columns);

            $rows[] = new PdbIndex([
                'name' => $row[0],
                'type' => strtolower($unique ?: 'index'),
                'columns' => array_combine($columns, $columns),
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function getForeignKeys(string $table)
    {
        $q = "SELECT
                k1.constraint_name,
                k1.table_name,
                k1.column_name,
                k2.table_name AS referenced_table_name,
                k2.column_name AS referenced_column_name,
                fk.update_rule,
                fk.delete_rule
            FROM information_schema.key_column_usage AS k1
            JOIN information_schema.referential_constraints AS fk
                USING (constraint_schema, constraint_name)
            JOIN information_schema.key_column_usage AS k2
                ON k2.constraint_schema = fk.unique_constraint_schema
                AND k2.constraint_name = fk.unique_constraint_name
                AND k2.ordinal_position = k1.position_in_unique_constraint
            WHERE k1.constraint_name != ''
                AND k1.table_schema = ?
                AND k1.table_name = ?
            ORDER BY
                k1.table_name,
                k1.column_name
        ";

        $params = [
            $this->config->schema,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');

        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => $row[0],
                'from_table' => $this->stripPrefix($row[1]),
                'from_column' => $row[2],
                'to_table' => $this->stripPrefix($row[3]),
                'to_column' => $row[4],
                'update_rule' => $row[5],
                'delete_rule' => $row[6],
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function getDependentKeys(string $table)
    {
        $q = "SELECT
                k1.constraint_name,
                k1.table_name,
                k1.column_name,
                k2.table_name AS referenced_table_name,
                k2.column_name AS referenced_column_name,
                fk.update_rule,
                fk.delete_rule
            FROM information_schema.key_column_usage AS k1
            JOIN information_schema.referential_constraints AS fk
                USING (constraint_schema, constraint_name)
            JOIN information_schema.key_column_usage AS k2
                ON k2.constraint_schema = fk.unique_constraint_schema
                AND k2.constraint_name = fk.unique_constraint_name
                AND k2.ordinal_position = k1.position_in_unique_constraint
            WHERE k1.constraint_name != ''
                AND k1.table_schema = ?
                AND k2.table_name = ?
                AND k2.column_name = 'id'
                AND fk.delete_rule = 'CASCADE'
            ORDER BY
                k1.table_name,
                k1.column_name
        ";

        $params = [
            $this->config->schema,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => $row[0],
                'from_table' => $this->stripPrefix($row[1]),
                'from_column' => $row[2],
                'to_table' => $this->stripPrefix($row[3]),
                'to_column' => $row[4],
                'update_rule' => $row[4],
                'delete_rule' => $row[5],
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function getTableAttributes(string $table)
    {
        return [];
    }


    /** @inheritdoc */
    public function extractEnumArr(string $table, string $column)
    {
        // TODO
        // To use CREATE TYPE ENUM ?
        // Or CHECK ("column" IN (...))
        return [];
    }

}
