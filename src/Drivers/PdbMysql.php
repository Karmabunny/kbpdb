<?php

namespace karmabunny\pdb\Drivers;

use InvalidArgumentException;
use karmabunny\pdb\Exceptions\ConnectionException;
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
class PdbMysql extends Pdb
{

    /**
     *
     * @param PdbConfig|array $config
     * @return PDO
     * @throws ConnectionException
     * @throws InvalidArgumentException
     */
    public static function connect($config)
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        $pdo = parent::connect($config);
        $hacks = $config->getHacks();

        if ($hacks[PdbConfig::HACK_NO_ENGINE_SUBSTITUTION] ?? false) {
            $pdo->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        }

        if (is_string($tz = $hacks[PdbConfig::HACK_TIME_ZONE] ?? false)) {
            if (!is_string($tz) or !preg_match('!^[^/0-9]+/[^/0-9]+$!', $tz)) {
                // @phpstan-ignore-next-line
                $tz = is_scalar($tz) ? (string) $tz : gettype($tz);
                throw new InvalidArgumentException("Invalid time zone: {$tz}");
            }

            $tz = $pdo->quote($tz, PDO::PARAM_STR);
            $pdo->query("SET SESSION time_zone = {$tz}");
        }

        return $pdo;
    }


    /**
     * Is this a MariaDB connection?
     *
     * @return bool
     * @throws ConnectionException
     */
    public function isMariadb(): bool
    {
        $version = $this->getServerVersion();
        return strpos(strtolower($version), 'mariadb') !== false;
    }


    /** @inheritdoc */
    public function getPermissions()
    {
        $q = "SHOW GRANTS FOR CURRENT_USER()";
        $res = $this->query($q, [], 'col');

        $perms = [];

        foreach ($res as $val) {
            $matches = [];
            if (!preg_match('/GRANT (.+) ON/', $val, $matches)) continue;

            $matches = explode(',', strtoupper($matches[1]));
            $matches = array_filter($matches);
            if (empty($matches)) continue;

            array_push($perms, ...$matches);
        }

        $perms = array_map('trim', $perms);

        if (in_array('ALL PRIVILEGES', $perms)) {
            return PdbHelpers::TYPES;
        }

        // Of the available types, which do we have?
        return array_intersect(PdbHelpers::TYPES, $perms);
    }


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
    public function getTableNames(string $filter = '*', bool $strip = true)
    {
        $q = "SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
        ";

        $params = [$this->config->database];
        $res = $this->query($q, $params, 'col');

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
                CHARACTER_SET_NAME,
                EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['COLUMN_NAME'];
            $autoinc = stripos($row['EXTRA'], 'auto_increment') !== false;

            $is_nullable = $row['IS_NULLABLE'] == 'YES';
            $default = $row['COLUMN_DEFAULT'];

            if ($default) {
                $default = trim($row['COLUMN_DEFAULT'], '\'');

                // - MariaDB will return a 'null' string.
                // - MySQL will return an actual NULL.
                // So yeah - you can't set a string 'null'. But why would you?
                if (strcasecmp($default, 'null') === 0) {
                    $default = null;
                }

                // Convert numbers.
                if (is_numeric($default)) {
                    if (stripos($row['COLUMN_TYPE'], 'int')) {
                        $default = (int) $default;
                    } else {
                        $default = (float) $default;
                    }
                }
            }

            $rows[$key] = new PdbColumn([
                'name' => $row['COLUMN_NAME'],
                'type' => $row['COLUMN_TYPE'],
                'is_nullable' => $is_nullable,
                'is_primary' => $row['COLUMN_KEY'] == 'PRI',
                'default' => $default,
                'auto_increment' => $autoinc,
                'extra' => $row['EXTRA'],
                'attributes' => [
                    'charset' => $row['CHARACTER_SET_NAME'],
                ],
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
                sum(NON_UNIQUE) AS NON_UNIQUE,
                group_concat(COLUMN_NAME) as COLUMNS
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND INDEX_NAME != 'PRIMARY'
            GROUP BY INDEX_NAME
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $res = $this->query($q, $params, 'pdo');
        $rows = [];

        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $columns = explode(',', $row['COLUMNS']);

            $rows[] = new PdbIndex([
                'name' => $row['INDEX_NAME'],
                'type' => $row['NON_UNIQUE'] == 0 ? 'unique' : 'index',
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

        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => $row['CONSTRAINT_NAME'],
                'from_table' => $this->stripPrefix($row['TABLE_NAME']),
                'from_column' => $row['COLUMN_NAME'],
                'to_table' => $this->stripPrefix($row['REFERENCED_TABLE_NAME']),
                'to_column' => $row['REFERENCED_COLUMN_NAME'],
                'update_rule' => $row['UPDATE_RULE'],
                'delete_rule' => $row['DELETE_RULE'],
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
                C.UPDATE_RULE,
                C.DELETE_RULE
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

        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = new PdbForeignKey([
                'constraint_name' => $row['CONSTRAINT_NAME'],
                'from_table' => $this->stripPrefix($row['TABLE_NAME']),
                'from_column' => $row['COLUMN_NAME'],
                'to_table' => $this->stripPrefix($row['REFERENCED_TABLE_NAME']),
                'to_column' => $row['REFERENCED_COLUMN_NAME'],
                'update_rule' => $row['UPDATE_RULE'],
                // always 'CASCADE'.
                'delete_rule' => $row['DELETE_RULE'],
            ]);
        }

        $res->closeCursor();
        return $rows;
    }


    /** @inheritdoc */
    public function getTableAttributes(string $table)
    {
        $q = "SELECT
                T.CREATE_TIME,
                T.UPDATE_TIME,
                T.ENGINE,
                T.TABLE_COLLATION,
                T.TABLE_COMMENT,
                T.AUTO_INCREMENT,
                C.CHARACTER_SET_NAME
            FROM INFORMATION_SCHEMA.TABLES AS T
            INNER JOIN INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY AS C
                ON T.TABLE_COLLATION = C.COLLATION_NAME
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
        ";

        $params = [
            $this->config->database,
            $this->config->prefix . $table,
        ];

        $row = $this->query($q, $params, 'row');
        return array_change_key_case($row, CASE_LOWER);
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
