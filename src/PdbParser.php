<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DOMAttr;
use DOMDocument;
use DOMElement;
use Exception;
use karmabunny\kb\XML;
use karmabunny\kb\XMLAssertException;
use karmabunny\kb\XMLException;
use karmabunny\pdb\Models\PdbColumn;
use karmabunny\pdb\Models\PdbForeignKey;
use karmabunny\pdb\Models\PdbIndex;
use karmabunny\pdb\Models\PdbTable;

/**
* Provides a system for syncing a database to a database definition.
*
* The database definition is stored in one or more XML files, which get merged
* together before the sync is done.
* Contains code that may be MySQL specific.
**/
class PdbParser
{
    /**
     * MySQL names for the foreign key actions.
     */
    const FOREIGN_KEY_ACTIONS = [
        'restrict' => 'RESTRICT',
        'set-null' => 'SET NULL',
        'cascade' => 'CASCADE',
    ];


    /** @var PdbTable[] name => PdbTable */
    public $tables = [];

    /** @var string[] name => string */
    public $views = [];

    /** @var string[][] name => string[] */
    private $errors = [];


    /**
     * Loads the XML definition file
     *
     * @param string|DOMDocument $dom DOMDocument or filename to load.
     * @return void
     * @throws XMLException
     * @throws Exception
     **/
    public function loadXml($dom)
    {
        // If its not a DOMDocument, assume a filename
        if (!($dom instanceof DOMDocument)) {
            $dom = XML::parse(file_get_contents($dom), [
                'filename' => $dom,
                'validate' => file_get_contents(__DIR__ . '/db_struct.xsd'),
            ]);
        }

        // Tables
        $table_nodes = XML::xpath($dom, './table', 'list');

        foreach ($table_nodes as $table_node) {
            /** @var DOMElement $table_node */

            $table_name = $table_node->getAttribute('name');

            if (!$table_name) {
                $this->errors[$table_name][] = 'A table exists in the xml without a defined name';
                continue;
            }

            $this->tables[$table_name] = $this->parseTable($table_node);
        }

        // Views
        $views_nodes = XML::xpath($dom, './view', 'list');
        foreach ($views_nodes as $view_node) {
            /** @var DOMElement $view_node */

            $view_name = $view_node->getAttribute('name');

            if (!$view_name) {
                $this->errors[$view_name][] = 'A view exists in the xml without a defined name';
                continue;
            }

            $this->views[$view_name] = $this->parseView($view_node);
        }
    }


    /**
     *
     * @param DOMElement $table_node
     * @return PdbTable
     * @throws XMLAssertException
     */
    private function parseTable(DOMElement $table_node)
    {
        $table_name = $table_node->getAttribute('name');

        /** @var PdbTable|null $table */
        $table = $this->tables[$table_name] ?? null;

        // If the table doesn't exist yet in memory, create it
        // It may already exist if doing a cross-module db structure merge
        if (!$table) {
            $table = new PdbTable([
                'name' => $table_name,
                'previous_names' => self::parseStringArray($table_node->getAttribute('previous-names')),
            ]);

            $engine = $table_node->getAttribute('engine');
            $charset = $table_node->getAttribute('charset');
            $collate = $table_node->getAttribute('collate');

            if ($engine) $table->attributes['engine'] = $engine;
            if ($charset) $table->attributes['charset'] = $charset;
            if ($collate) $table->attributes['collate'] = $collate;
        }


        // Columns
        $column_nodes = XML::xpath($table_node, './column', 'list');
        foreach ($column_nodes as $node) {
            /** @var DOMElement $node */

            $type = self::parseColumnType($node);

            // TODO Update to use kbphp (v2.16) XML.

            $is_nullable = true;
            if ($node->hasAttribute('allownull')) {
                $is_nullable = (bool) $node->getAttribute('allownull');
            }

            $auto_increment = false;
            if ($node->hasAttribute('autoinc')) {
                $auto_increment = (bool) $node->getAttribute('autoinc');
            }

            $default = null;
            if ($node->hasAttribute('default')) {
                $default = $node->getAttribute('default');

                // Convert numbers.
                if (is_numeric($default)) {
                    if (stripos($type, 'int')) {
                        $default = (int) $default;
                    } else {
                        $default = (float) $default;
                    }
                }
            }

            $table->addColumn(new PdbColumn([
                'name' => $node->getAttribute('name'),
                'type' => $type,
                'is_nullable' => $is_nullable,
                'auto_increment' => $auto_increment,
                'default' => $default,
                'previous_names' => self::parseStringArray($node->getAttribute('previous-names')),
            ]));
        }


        // Indexes
        $index_nodes = XML::xpath($table_node, './index', 'list');
        foreach ($index_nodes as $index_node) {
            /** @var DOMElement $index_node */

            $index = new PdbIndex([
                'type' => strtolower($index_node->getAttribute('type')) ?: 'index',
            ]);

            // The schema ensures we only have 0 or 1.
            // In this case, we only need one 'col'.
            // TODO I guess we should freak if there's more than one.
            $fk_node = XML::first($index_node, 'foreign-key');
            if ($fk_node) {
                $col_node = XML::expectFirst($index_node, 'col');

                $col_name = $col_node->getAttribute('name');
                $index->columns[$col_name] = $col_name;

                $table->foreign_keys[] = new PdbForeignKey([
                    'from_table' => $table_name,
                    'from_column' => $col_name,
                    'to_table' => $fk_node->getAttribute('table'),
                    'to_column' => $fk_node->getAttribute('column'),
                    'update_rule' => self::parseConstraintAction($fk_node->getAttribute('update')),
                    'delete_rule' => self::parseConstraintAction($fk_node->getAttribute('delete')),
                ]);
            }
            // Non-FK indexes.
            // TODO Hey, do other DBs require the FK to be an index?
            else {
                $col_nodes = $index_node->getElementsByTagName('col');
                foreach ($col_nodes as $node) {
                    /** @var DOMElement $node */

                    $col_name = $node->getAttribute('name');
                    $index->columns[$col_name] = $col_name;
                }
            }

            $table->indexes[] = $index;
        }

        // Does this table already exist? Stop here.
        // Only columns and indexes can be merged across XML files.
        if (isset($this->tables[$table_name])) {
            return $table;
        }


        // Attributes (engine, charset, etc)
        foreach ($table_node->attributes as $attr) {
            /** @var DOMAttr $attr */
            $table->attributes[$attr->name] = $attr->value;
        }


        // Primary keys
        $primary_nodes = XML::xpath($table_node, './primary/col', 'list');

        foreach ($primary_nodes as $node) {
            /** @var DOMElement $node */
            $col_name = $node->getAttribute('name');
            $table->primary_key[] = $col_name;

            // Also add in the 'is_primary' flag while we're here.
            /** @var PdbColumn|null $col */
            $col = &$table->columns[$col_name] ?? null;
            if ($col) $col->is_primary = true;
        }


        // Default records
        $default_records = XML::xpath($table_node, './default_records/record', 'list');

        foreach ($default_records as $record) {
            $record_attrs = [];

            foreach ($record->attributes as $attr) {
                /** @var DOMAttr $attr */
                $record_attrs[$attr->name] = $attr->value;
            }

            $table->default_records[] = $record_attrs;
        }

        return $table;
    }



    /**
     *
     * @param DOMElement $view_node
     * @return string
     */
    private function parseView(DOMElement $view_node)
    {
        return XML::text($view_node);
    }


    /**
     * Run a sanity check over all loaded tables
     *
     * @return bool
     */
    public function sanityCheck()
    {
        foreach ($this->tables as $table) {
            $errors = $table->check($this->tables);

            if (!empty($errors)) {
                $this->errors[$table->name] = $errors;
            }
        }

        return $this->hasErrors();
    }


    /**
     * Were there any load or sanity check errors?
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }


    /**
     * Get a list of errors.
     *
     * @return string[][] table => errors[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }


    /**
     * Get a formatted error log.
     *
     * @return array
     */
    public function getErrorsLog(): array
    {
        $log = [];
        $log[] = [ 'section', 'Load Errors' ];

        foreach ($this->errors as $table => $errors) {
            $log[] = [ 'heading', $table ];

            foreach ($errors as $error) {
                $log[] = [ 'message', $error ];
            }
        }

        return $log;
    }


    /**
     * Extract a list of strings from a string.
     *
     * The results are all lowercase + trimmed.
     *
     * @param string $value
     * @return string[]
     */
    public static function parseStringArray(string $value): array
    {
        if (empty($value)) return [];

        $items = explode(',', $value);
        // return array_map('trim', $items);

        foreach ($items as &$item) {
            $item = strtolower(trim($item));
        }
        unset($item);
        return $items;
    }


    /**
     * Get a list of strings from the target child element.
     *
     * TODO Update to use kbphp (v2.16) XML.
     *
     * @param DOMElement $node
     * @param string $tag
     * @return string[]
     */
    public static function parseValues(DOMElement $node, string $tag): array
    {
        /** @var DOMElement[] $items */
        $items = XML::xpath($node, './' . $tag, 'list');
        return array_map([XML::class, 'text'], $items);
    }


    /**
     * Parse the column type definition.
     *
     * In particular this will parse XML-style enum/set types.
     *
     * Note: This _does not_ normalise the types. You must use the
     * {@see PdbHelper::normalizeType()} method before comparing types.
     *
     * @param DOMElement $node
     * @return string
     */
    public static function parseColumnType(DOMElement $node): string
    {
        $type = $node->getAttribute('type');
        $type = trim($type);

        switch (strtoupper($type)) {
            case 'ENUM':
            case 'SET':
            case 'ENUM(XML)':
            case 'SET(XML)':
                $values = self::parseValues($node, 'val');
                foreach ($values as &$value) {
                    $value = addcslashes($value, '\'');
                    $value = "'{$value}'";
                }
                unset($value);
                return str_replace('xml', implode(',', $values), $type);

            default:
                return $type;
        }
    }


    /**
     *
     * @param string $action
     * @return string
     */
    public static function parseConstraintAction(string $action): string
    {
        return self::FOREIGN_KEY_ACTIONS[$action] ?? '';
    }
}
