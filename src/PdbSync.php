<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use Exception;
use Generator;
use InvalidArgumentException;
use karmabunny\kb\Uuid;
use karmabunny\pdb\Drivers\PdbMysql;
use karmabunny\pdb\Drivers\PdbSqlite;
use karmabunny\pdb\Exceptions\PdbException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Models\SyncActions;
use karmabunny\pdb\Models\PdbTable;
use karmabunny\pdb\Models\SyncFix;
use karmabunny\pdb\Models\SyncQuery;
use PDOException;
use Throwable;

/**
* Provides a system for syncing a database to a database definition.
*
* The database definition is stored in one or more XML files, which get merged
* together before the sync is done.
* Contains code that may be MySQL specific.
**/
class PdbSync
{
    /** @var Pdb */
    private $pdb;


    /**
     * Temporarily stores heading to attach to next query generated.
     */
    private $heading = 'TBA';

    /** @var SyncQuery[][] type => queries[] */
    private $queries = [];

    /** @var SyncFix[][] type  => fixes[] */
    private $fixes = [];

    /** @var string[] */
    private $warnings = [];


    /**
     * Types of queries generated by running a sync, in the order that they should be run.
     *
     * @var string[]
     */
    const QUERY_TYPES = [
        'alter_table',
        'alter_column',
        'rename_table',
        'rename_col',
        'drop_fk',
        'drop_index',
        'drop_column',
        'add_table',
        'insert_record',
        'add_column',
        'add_index',
        'alter_pk',
        'add_fk',
        'views',
    ];


    /**
     * @param Pdb|PdbConfig|array $config
     **/
    public function __construct($config)
    {
        if ($config instanceof Pdb) {
            $this->pdb = $config;
        }
        else {
            $this->pdb = Pdb::create($config);
        }
    }


    /**
    * Are the permissions of the current user adequate?
    *
    * @return true|string[] true on success, or an array of missing permissions on failure.
    **/
    public function checkConnPermissions()
    {
        $permissions = $this->pdb->getPermissions();

        $require = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP'];
        $missing = array_diff($require, $permissions);
        if (count($missing) == 0) return true;

        return $missing;
    }


    /**
     *
     * @param PdbParser $parser
     * @param SyncActions|array $do
     * @return array [ type, body ]
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Exception
     * @throws QueryException
     */
    public function updateDatabase(PdbParser $parser, $do = null)
    {
        $this->migrate($parser, $do);
        return $this->execute();
    }


    /**
     *
     * @param PdbParser $parser
     * @param mixed|null $do
     * @return void
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Exception
     * @throws QueryException
     */
    public function migrate(PdbParser $parser, $do = null)
    {
        // Mush it.
        if (is_array($do)) {
            $do = new SyncActions($do);
        }
        // All actions.
        else if ($do === null) {
            $do = new SyncActions();
        }

        $this->migrateTables($parser->tables, $do);

        if ($do->views) {
            $this->migrateViews($parser->views);
        }
    }


    /**
     * Prepare a set of ALTER TABLE statements which will sync the database to the provided layout.
     *
     * @param PdbTable[] $tables
     * @param SyncActions|array $do
     * @return void
     */
    public function migrateTables(array $tables, $do = null)
    {
        // Mush it.
        if (is_array($do)) {
            $do = new SyncActions($do);
        }
        // All actions.
        else if ($do === null) {
            $do = new SyncActions();
        }

        foreach ($tables as $table) {
            // Check the table exists
            $new_table = false;

            if ($do['create']) {
                if (! $this->checkTableMatches($table)) {
                    $new_table = true;
                }
            }

            // Check the primary key patches
            if (!$new_table and $do['primary']) {
                $this->checkPrimaryMatches($table);
            }

            // Check each column
            if (!$new_table and $do['column']) {
                $prev_column = '';

                foreach ($table->columns as $column) {
                    $this->checkColumnMatches($table->name, $column, $prev_column);
                    $prev_column = $column->name;
                }
            }

            // Check each index
            if (!$new_table and $do['index']) {
                foreach ($table->indexes as $index) {
                    $this->checkIndexMatches($table->name, $index);
                }
            }

            // Check each foreign key
            if ($do['foreign_key']) {
                foreach ($table->foreign_keys as $foreign_key) {
                    $this->checkForeignKeyMatches($table->name, $foreign_key);
                }
            }

            // Remove old columns
            if (!$new_table and $do['remove']) {
                $this->checkRemovedColumns($table->name, $table->columns);
                $this->checkRemovedIndexes($table->name, $table->indexes);
                $this->checkRemovedForeignKeys($table->name, $table->foreign_keys);
            }
        }
    }


    /**
     *
     * @param string[] $views [name => SQL]
     * @return void
     * @throws InvalidArgumentException
     */
    public function migrateViews(array $views)
    {
        foreach ($views as $view_name => $view_def) {
            $this->createView($view_name, $view_def);
        }
    }


    /**
     *
     * @return Generator<SyncQuery>
     */
    public function getQueries()
    {
        foreach (self::QUERY_TYPES as $type) {
            if (empty($this->queries[$type])) continue;

            foreach ($this->queries[$type] as $query) {
                yield $query;
            }
        }
    }


    /**
     * Execute the stored queries.
     *
     * @return array [ type, body ] execution log
     */
    public function execute($act = true)
    {
        $log = [];
        $log[] = [ 'section', 'Tables' ];

        /** @var Throwable[] $errors */
        $errors = [];

        $heading = '';
        foreach ($this->getQueries() as $query) {

            if ($heading !== $query->heading) {
                $heading = $query->heading;
                $log[] = [ 'heading', $query->heading ];
            }

            try {
                $log[] = [ 'query', $query->query ];
                if ($act) {
                    $this->pdb->query($query->query, [], 'pdo');
                }
            }
            catch (Throwable $error) {
                $log[] = [ 'message', $error->getMessage() ];
                $errors[] = $error;
            }

            if ($query->message) {
                $log[] = [ 'message', $query->message ];
            }
        }

        if (!empty($this->warnings)) {
            $log[] = [ 'section', 'Warnings' ];
        }

        foreach ($this->warnings as $warning) {
            $logs[] = [ 'message', $warning ];
        }

        if (!empty($this->warnings)) {
            $log[] = [ 'section', 'Fixes' ];
        }

        foreach ($this->fixes as $type => $fixes) {
            $log[] = [ 'heading', $type ];

            foreach ($fixes as $fix) {
                $log[] = [ 'message', $fix->name ];
                $log[] = [ 'query', $fix->query ];
            }
        }

        if (!empty($errors)) {
            $logs[] = [ 'section', 'Errors' ];
        }

        foreach ($errors as $error) {
            $log[] = [ 'message', $error->getMessage() ];
        }

        return $log;
    }


    /**
     * Create a dump of SQL that can be executed later.
     *
     * @return string[]
     */
    public function getMigration()
    {
        $sql = [];

        $heading = '';
        foreach ($this->getQueries() as $query) {

            // Print headings as comments.
            if ($heading !== $query->heading) {
                $heading = $query->heading;
                $sql[] = '-- ' . $query->heading;
            }

            // The juicy bits.
            $sql[] = $query->query;

            // Some queries have extra messages, also comments.
            if ($query->message) {
                $sql[] = '-- > ' . $query->message;
            }
        }

        // Print warnings as comments.
        if (!empty($this->warnings)) {
            $sql[] = '-- Warnings';
        }

        foreach ($this->warnings as $warning) {
            $sql[] = '-- ! ' . $warning;
        }

        // Print fixes as comments.
        if (!empty($this->warnings)) {
            $sql[] = '-- Fixes';
        }

        foreach ($this->fixes as $type => $fixes) {
            $sql[] = '-- ! Broken ' . $type;

            foreach ($fixes as $fix) {
                $sql[] = '-- ' . $fix;
            }
        }

        return $sql;
    }


    /**
     * Checks that the specified table exists
     * If the table does not exist, it will be created.
     *
     * @param PdbTable $table The table definition
     *
     * @return bool False if the table did not exist and was just then created, true otherwise
     */
    private function checkTableMatches($table)
    {
        // If the table does not exist, create it
        if (! $this->pdb->tableExists($table->name)) {

            // Search previous names for a match; if found the table is renamed
            foreach ($table->previous_names as $old_name) {
                if (!$this->pdb->tableExists($old_name)) continue;

                // Found one.
                $this->heading = "RENAME - Table '{$old_name}' to '{$table->name}'";

                $q = "RENAME TABLE ~{$old_name} TO ~{$table->name}";
                $this->storeQuery('rename_table', $q);

                // Done!
                return false;
            }

            // Otherwise...
            $this->heading = "MISSING - Table '{$table->name}'";
            $this->createTable($table);

            return false;
        }

        // Some conditional stuff for MySQL tables.
        if ($this->pdb instanceof PdbMysql) {
            $attributes = $this->pdb->getTableAttributes($table->name);

            $charset = $table->attributes['charset'];
            $engine = $table->attributes['engine'];
            $collate = $table->attributes['collate'];

            $bad_engine = false;
            if ($attributes['engine'] != $engine) {
                $bad_engine = true;
            }

            $bad_collate = false;
            if ($attributes['table_collation'] != $collate) {
                $bad_collate = true;
            }

            if ($bad_engine or $bad_collate) {
                $this->heading = "ATTRS - Table '{$table->name}'";

                $q = "ALTER TABLE ~{$table->name}";
                $q .= " ENGINE = {$engine},";
                $q .= " CHARACTER SET = {$charset},";
                $q .= " COLLATE = {$collate}";

                $this->storeQuery('alter_table', $q);

                $q = "ALTER TABLE ~{$table->name}";
                $q .= " CONVERT TO CHARACTER SET {$charset}";
                $q .= " COLLATE {$collate}";

                $this->storeQuery('alter_table', $q);
            }
        }

        return true;
    }


    /**
     * Creates a table which matches the specified definition
     *
     * @param PdbTable $table
     * @return bool
     */
    private function createTable(PdbTable $table)
    {
        Pdb::validateIdentifier($table->name);

        $defs = [];

        foreach ($table->columns as $column) {
            $spec = $this->createSqlColumnSpec($column);
            $defs[] = $this->pdb->quote($column->name, Pdb::QUOTE_FIELD) . ' ' . $spec;
        }

        if ($table->primary_key) {
            $primary_key = implode(', ', $this->pdb->quoteAll($table->primary_key, Pdb::QUOTE_FIELD));
            $defs[] = "PRIMARY KEY ({$primary_key})";
        }

        foreach ($table->indexes as $index) {
            $type = strtoupper($index->type);
            $columns = implode(', ', $this->pdb->quoteAll($index->columns, Pdb::QUOTE_FIELD));
            $defs[] = "{$type} ({$columns})";
        }

        $q = "CREATE TABLE ~{$table->name} (\n";
        $q .= '  ' . implode(",\n  ", $defs) . "\n";
        $q .= ")\n";

        if ($this->pdb instanceof PdbMysql) {
            $q .= " ENGINE = {$table->attributes['engine']},";
            $q .= " CHARACTER SET = {$table->attributes['charset']},";
            $q .= " COLLATE = {$table->attributes['collate']}";
        }

        // $msg = ($this->act ? 'Table created successfully' : '');
        $this->storeQuery('add_table', $q);


        // Default records
        foreach ($table->default_records as $record) {

            $cols = [];
            $vals = [];

            foreach ($record as $col => $val) {
                $cols[] = $col;

                switch (strtolower($val)) {
                    case 'now()':
                        $vals[] = Pdb::now();
                        break;

                    case 'uuid()':
                        $vals[] = Uuid::uuid4();
                        break;

                    default:
                        $vals[] = $val;
                        break;
                }
            }

            $cols = implode(', ', $this->pdb->quoteAll($cols, Pdb::QUOTE_FIELD));
            $vals = implode(', ', $this->pdb->quoteAll($vals, Pdb::QUOTE_VALUE));

            $q = "INSERT INTO ~{$table->name} ({$cols}) VALUES ({$vals})";
            $this->storeQuery('insert_record', $q);
        }

        return true;
    }


    /**
     * Checks that the specified primary key is correct for the table
     *
     * @param PdbTable $table
     * @return bool
     */
    private function checkPrimaryMatches(PdbTable $table)
    {
        $columns = $this->pdb->fieldList($table->name);

        $key = [];
        foreach ($columns as $col) {
            if ($col->is_primary) {
                $key[] = $col->name;
            }
        }

        if ($key == $table->primary_key) return true;

        $this->heading = "PRIMARY - Table '{$table->name}";
        $this->changePrimary($table);
        return false;
    }


    /**
     * Changes the primary key of a table
     *
     * @param PdbTable $table
     * @return bool
     */
    private function changePrimary(PdbTable $table)
    {
        $primary = $this->pdb->quoteAll($table->primary_key, Pdb::QUOTE_FIELD);
        $columns = implode(', ', $primary);

        $q = "ALTER TABLE ~{$table->name} DROP PRIMARY KEY, ADD PRIMARY KEY ({$columns})";
        $this->storeQuery('alter_pk', $q);

        return true;
    }


    /**
     * Checks that the specified column exists in the specified table
     * If the column is incorrect, it will be altered
     * If the column does not exist, it will be created
     *
     * @param string $table_name The name of the table to check
     * @param PdbColumn $column The column definition to check
     * @param string $prev_column The name of the previous column, for column positioning
     * @return bool
     */
    private function checkColumnMatches(string $table_name, PdbColumn $column, string $prev_column)
    {
        $columns = $this->pdb->fieldList($table_name);
        $col = $columns[$column->name] ?? null;

        // If not found, create it.
        if ($col === null) {
            $spec = $this->createSqlColumnSpec($column);

            // Search previous names for a match; if found the column is renamed
            foreach ($column->previous_names as $old_name) {
                if (!isset($column[$old_name])) continue;

                $old_name = $this->pdb->quote($old_name, Pdb::QUOTE_FIELD);
                $new_name = $this->pdb->quote($column->name, Pdb::QUOTE_FIELD);

                $q = "ALTER TABLE ~{$table_name} CHANGE COLUMN {$old_name} {$new_name} {$spec}";

                $this->heading = "RENAME - Column '{$old_name}' to '{$column->name}'";
                $this->storeQuery('rename_col', $q);
                return true;
            }

            // Otherwise - create the column from scratch.

            $name = $this->pdb->quote($column->name, Pdb::QUOTE_FIELD);
            $q = "ALTER TABLE ~{$table_name} ADD COLUMN {$name} {$spec}";

            if ($this->pdb instanceof PdbMysql) {
                // Use the MySQL-only AFTER syntax where possible
                if ($prev_column) {
                    $q .= " AFTER {$prev_column}";
                }
            }

            $this->heading = "MISSING - Table '{$table_name}', Column '{$column->name}'";
            $this->storeQuery('add_column', $q);
            return true;
        }

        $old_type = PdbHelpers::normalizeType($col->type);
        $new_type = PdbHelpers::normalizeType($column->type);

        // if (strpos($old_type, 'INT') !== false) {
        //     $col['Default'] = (int) $col['Default'];
        //     $column['default'] = (int) $column['default'];
        // }

        $errors = [];

        if ($old_type != $new_type) {
            $errors[] = 'type';
        }
        if ($col->is_nullable !== $column->is_nullable) {
            $errors[] = 'null';
        }
        if ($col->default != $column->default) {
            $errors[] = 'default';
        }
        if ($col->auto_increment !== $column->auto_increment) {
            $errors[] = 'autoinc';
        }

        if ($errors) {
            $reason = implode(', ', $errors);

            $name = $this->pdb->quote($column->name, Pdb::QUOTE_FIELD);
            $spec = $this->createSqlColumnSpec($column);
            $q = "ALTER TABLE ~{$table_name} MODIFY COLUMN {$name} {$spec}";

            $this->heading = "COLUMN - Table '{$table_name}', Column '{$column->name}' - {$reason}";
            $this->storeQuery('alter_column', $q);

            return true;
        }

        return true;
    }

    /**
     * Checks that the specified index exists in the table.
     *
     * - If the index is incorrect, it will be altered
     * - If the index does not exist, it will be created
     *
     * @param string $table_name The name of the table to check
     * @param PdbIndex $index The index definition to check
     * @return bool
     */
    private function checkIndexMatches(string $table_name, PdbIndex $index)
    {
        $indexes = $this->pdb->indexList($table_name);

        $existing = null;
        $action = 'create';

        // Find the matching index by the 'columns' - same order, etc.
        foreach ($indexes as $existing) {
            if ($existing->columns != $index->columns) {
                continue;
            }

            // If it exists and matches - there's nothing to do.
            if ($index->type == $existing->type) {
                return true;
            }

            $action = 'changetype';
            break;
        }

        $columns = implode(', ', $index->columns);
        $this->heading = "INDEX - Table '{$table_name}', Index ({$columns}) - " . $action;

        // Drop the old index before adding a new one.
        if ($existing and $action != 'create') {
            $q = "ALTER TABLE ~{$table_name} DROP INDEX {$existing->name}";
            $this->storeQuery('drop_index', $q);
        }

        // Sometimes it's a 'unique index'.
        $type = strtoupper($index->type);
        if ($type != 'INDEX') $type .= ' INDEX';

        $columns = implode(', ', $this->pdb->quoteAll($index->columns, Pdb::QUOTE_FIELD));
        $q = "ALTER TABLE ~{$table_name} ADD {$type} ({$columns})";
        $this->storeQuery('add_index', $q);

        return true;
    }


    /**
    * Checks that the specified foreign key exists in the table

    * - If the foreign key is incorrect, it will be altered
    * - If the foreign key does not exist, it will be created
    *
    * @param string $table_name The name of the table to check
    * @param PdbForeignKey $foreign_key The foreign key definition to check
    * @return bool
    **/
    private function checkForeignKeyMatches(string $table_name, PdbForeignKey $foreign_key)
    {
        $prefix = $this->pdb->getPrefix();
        $current_fks = $this->pdb->getForeignKeys($table_name);

        // Filter out non-matching FKs.
        foreach ($current_fks as $id => $fk) {
            if (
                $prefix . $foreign_key->to_table != $fk->to_table or
                $foreign_key->from_column != $fk->from_column or
                $foreign_key->to_column != $fk->to_column or
                $foreign_key->update_rule != $fk->update_rule or
                $foreign_key->delete_rule != $fk->delete_rule
            ) unset($current_fks[$id]);
        }

        // TODO Is there a reason we don't do this automatically?
        if (count($current_fks) > 1) {
            $names = [];

            foreach ($current_fks as $row) {
                $names[] = $row->constraint_name;

                $this->storeFix($row->constraint_name, [
                    'name' => 'Drop FK',
                    'query' => "ALTER TABLE ~{$table_name} DROP FOREIGN KEY {$row->constraint_name}",
                ]);
            }

            $names = implode(', ', $names);
            $this->storeWarning("Multiple foreign keys found for column {$foreign_key->from_column} - {$names})");
        }

        // Nothing to do? Because the FK already exists.
        if (count($current_fks) != 0) {
            return true;
        }

        $this->heading = "FRN KEY - Table '{$table_name}', Foreign key {$foreign_key->from_column} -> {$foreign_key->to_table}.{$foreign_key->to_column}";

        $from_column = $this->pdb->quote($foreign_key->from_column, Pdb::QUOTE_FIELD);
        $to_column = $this->pdb->quote($foreign_key->to_column, Pdb::QUOTE_FIELD);

        // Look for records which fail to join to the foreign table
        $message = '';
        try {
            $q = "SELECT COUNT(*)
                FROM ~{$table_name} AS main
                LEFT JOIN ~{$foreign_key->to_table} AS extant
                    ON main.{$from_column} = extant.{$to_column}
                WHERE extant.id IS NULL
            ";
            $num_invalid_records = $this->pdb->query($q, [], 'val');

            if ($num_invalid_records > 0) {
                $this->storeWarning(
                    "{$num_invalid_records} invalid records found:",
                    "in '{$table_name}' table (foreign key on '{$foreign_key->from_column}' column)"
                );

                $q = str_replace('COUNT(*)', "main.id, main.{$from_column}", $q);

                $this->storeFix($foreign_key->constraint_name, [
                    'name' => 'Find records',
                    'query' => $q,
                ]);

                $q = "DELETE FROM ~{$table_name}
                    WHERE {$from_column} NOT IN (SELECT id FROM ~{$foreign_key->to_table});
                ";

                $this->storeFix($foreign_key->constraint_name, [
                    'name' => 'Delete records',
                    'query' => $q,
                ]);

                $q = "UPDATE ~{$table_name}
                    SET {$from_column} = NULL
                    WHERE {$from_column} NOT IN (SELECT id FROM ~{$foreign_key->to_table});
                ";

                $this->storeFix($foreign_key->constraint_name, [
                    'name' => 'NULL offending values',
                    'query' => $q,
                ]);
            }
        } catch (QueryException $ex) {
            $this->storeWarning("Query error looking for invalid records; does the table '{$foreign_key->to_table}' exist?");
        }

        // Update the constraint.

        $q = "ALTER TABLE ~{$table_name} ADD FOREIGN KEY ({$from_column})
            REFERENCES ~{$foreign_key->to_table} ({$to_column})
            ON DELETE {$foreign_key->delete_rule}
            ON UPDATE {$foreign_key->update_rule}
        ";
        $this->storeQuery('add_fk', $q);

        return true;
    }


    /**
     * Find and remove unused columns.
     *
     * @param string $table_name
     * @param PdbColumn[] $defined
     * @return void
     *
     */
    private function checkRemovedColumns(string $table_name, array $defined)
    {
        $columns = $this->pdb->fieldList($table_name);

        foreach ($columns as $col) {
            foreach ($defined as $def_col) {
                // Skip columns that match.
                if ($def_col->name == $col->name) {
                    continue 2;
                }

                // Check column isn't an old name for a column which has been
                // or will be replaced upon sync.
                if (in_array($col->name, $def_col->previous_names)) {
                    continue 2;
                }
            }

            $q = "ALTER TABLE ~{$table_name} DROP COLUMN ";
            $q .= $this->pdb->quote($col->name, Pdb::QUOTE_FIELD);

            $this->heading = "REMOVED - Table '{$table_name}', Column '{$col->name}";
            $this->storeQuery('drop_column', $q);
        }
    }


    /**
     * Find and remove unused indexes.
     *
     * @param string $table_name
     * @param PdbIndex[] $defined
     * @return void
     **/
    private function checkRemovedIndexes(string $table_name, array $defined)
    {
        $indexes = $this->pdb->indexList($table_name);

        foreach ($indexes as $index) {
            if ($index->name == 'PRIMARY') continue;

            foreach ($defined as $def_index) {
                if (
                    strcasecmp($index->type, $def_index->type) === 0 and
                    $def_index->columns == $index->columns
                ) continue 2;
            }

            $q = "ALTER TABLE ~{$table_name} DROP INDEX ";
            $q .= $this->pdb->quote($index->name, Pdb::QUOTE_FIELD);

            $this->heading = "REMOVED - Table '{$table_name}', Index '{$index->name}'";
            $this->storeQuery('drop_index', $q);
        }
    }


    /**
     * Find and remove unused foreign keys
     *
     * @param string $table_name
     * @param PdbForeignKey[] $defined
     **/
    private function checkRemovedForeignKeys(string $table_name, array $defined)
    {
        $current_fks = $this->pdb->getForeignKeys($table_name);

        $prefix = $this->prefix;

        foreach ($current_fks as $fk) {

            foreach ($defined as $def_fk) {
                if (
                    $def_fk->from_column == $fk->from_column and
                    $prefix . $def_fk->to_table == $fk->to_table and
                    $def_fk->to_column == $fk->to_column and
                    $def_fk->update_rule == $fk->update_rule and
                    $def_fk->delete_rule == $fk->delete_rule
                ) continue 2;
            }

            $q = "ALTER TABLE ~{$table_name} DROP FOREIGN KEY {$fk->constraint_name}";

            $this->heading = "REMOVED - Table '{$table_name}', Foreign key '{$fk->constraint_name}'";
            $this->storeQuery('drop_fk', $q);
        }
    }


    /**
    * Create or update a view
    **/
    private function createView(string $view_name, string $view_def)
    {
        $view_name = trim($view_name);
        $view_def = trim($view_def);

        $q = "DROP VIEW ~{$view_name}";
        $this->storeQuery('views', $q);

        $q = "CREATE VIEW ~{$view_name}\nAS\n\t{$view_def}";
        $this->storeQuery('views', $q);
    }


    /**
     * Stores a query in a list of queries to run.
     *
     * TODO $message isn't used anywhere.
     * Perhaps we could fold in fixes and warnings here.
     * Like, temp store fixes/warnings, copy into the stored query and clear the temp.
     *
     * TODO Should throw if the 'heading' in missing.
     *
     * @param string $type One of QUERY_TYPES
     * @param string $query
     * @param string $message
     * @return void
     * @throws InvalidArgumentException if unknown query type
     */
    private function storeQuery(string $type, string $query, $message = '')
    {
        if (!in_array($type, self::QUERY_TYPES)) {
            throw new InvalidArgumentException('Unknown query type: ' . $type);
        }

        $this->queries[$type][] = new SyncQuery([
            'query' => $query,
            'heading' => $this->heading,
            'message' => $message,
        ]);
    }


    /**
     * Store a 'fix query'.
     *
     * These are queries that require user input.
     *
     * TODO Hey we could actually _execute_ these automatically.
     * With like a 'force' option. Some would have to be marked 'before' and
     * 'after' actions. For the FK delete/null - there would need to be a default.
     *
     * @param string $key
     * @param SyncFix|array $fix
     * @return void
     */
    private function storeFix(string $key, $fix)
    {
        if (is_array($fix)) {
            $fix = new SyncFix($fix);
        }
        $this->fixes[$key][] = $fix;
    }


    /**
     * User messages.
     *
     * Display these after the SQL log.
     *
     * @param string|string[] $warnings
     * @return void
     */
    private function storeWarning($warnings)
    {
        if (!is_array($warnings)) {
            $warnings = [ $warnings ];
        }
        array_push($this->warnings, ...$warnings);
    }


    /**
    * Generates the SQL that should be used for a column spec
    *
    * The spec goes in the following location in the query:
    *    ALTER TABLE pizzas ADD COLUMN toppings <spec>
    *
    * @param PdbColumn $column
    * @return string
    **/
    private function createSqlColumnSpec(PdbColumn $column)
    {
        $spec = $column->type;

        if (!$column->is_nullable) {
            $spec .= ' NOT NULL';
        }

        if ($column->auto_increment) {
            if ($this->pdb instanceof PdbMysql) {
                $spec .= ' AUTO_INCREMENT';
            }

            if ($this->pdb instanceof PdbSqlite) {
                $spec .= ' AUTOINCREMENT';
            }

            // Postgres - SERIAL
            // Although technically a macro that only works for create tables.
            // Typically it's:
            // 1. create sequence
            // 2. create/alter table
            // 3. alter sequence
        }

        if ($column->default !== null) {
            $spec .= ' DEFAULT ' . $this->pdb->quote($column->default);
        }

        return $spec;
    }
}
