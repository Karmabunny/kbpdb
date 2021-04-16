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
use karmabunny\kb\Enc;
use karmabunny\kb\XML;
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
    /** @var PdbTable[] name => PdbTable */
    public $tables = [];

    /** @var array[] name => array */
    private $views = [];

    /** @var array[] name => string[] */
    private $errors = [];


    /**
    * Loads the XML definition file
    *
    * @param string|DOMDocument $dom DOMDocument or filename to load.
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
            if (!$table_name) throw new Exception('A table exists in the xml without a defined name');

            /** @var PdbTable|null $table */
            $table = &$this->tables[$table_name] ?? null;

            // If the table doesn't exist yet in memory, create it
            // It may already exist if doing a cross-module db structure merge
            if (!$table) {
                $table = new PdbTable([
                    'name' => $table_name,
                ]);
            }

            // Columns
            $column_nodes = XML::xpath($table_node, './column', 'list');
            foreach ($column_nodes as $node) {
                /** @var DOMElement $node */

                $table->addColumn(new PdbColumn([
                    'name' => $node->getAttribute('name'),
                    'type' => PdbHelpers::typeToUpper($node->getAttribute('type')),
                    'is_nullable' => (bool) $node->getAttribute('allownull'),
                    'auto_increment' => (int) $node->getAttribute('autoinc') ?: null,
                    'default' => $node->getAttribute('default') ?: null,
                    'previous_names' => explode(',', $node->getAttribute('previous-names')),
                ]));
            }

            // Indexes
            $index_nodes = XML::xpath($table_node, './index', 'list');
            foreach ($index_nodes as $index_node) {
                /** @var DOMElement $index_node */

                $index = new PdbIndex([
                    'type' => $index_node->getAttribute('type'),
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
                        'update_rule' => $fk_node->getAttribute('update'),
                        'delete_rule' => $fk_node->getAttribute('delete'),
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
            if (!isset($this->tables[$table_name])) {
                $this->tables[$table_name] = $table;
                continue;
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

            // End of table.
            $this->tables[$table_name] = $table;
        }

        // Views
        $views_nodes = XML::xpath($dom, './view', 'list');
        foreach ($views_nodes as $view_node) {
            /** @var DOMElement $view_node */

            $view_name = $view_node->getAttribute('name');
            if (!$view_name) throw new Exception('A view exists in the xml without a defined name');

            // @phpstan-ignore-next-line : I don't know what this achieves.
            $this->views[$view_name] = (string) $view_node->firstChild->data;
        }
    }


    /**
    * Run a sanity check over all loaded tables
    **/
    public function sanityCheck()
    {
        foreach ($this->tables as $table) {
            $errors = $table->check($this->tables);

            if (!empty($errors)) {
                $this->errors["table \"{$table->name}\""] = $errors;
            }
        }
    }


    /**
    * Were there any load or sanity check errors?
    **/
    public function hasErrors()
    {
        return !empty($this->errors);
    }


    /**
    * Return HTML of the load or sanity check errors
    *
    * @return string HTML
    **/
    public function getErrorsHtml()
    {
        $out = '';

        foreach ($this->errors as $file => $errors) {
            $out .= "<h3>Errors in " . Enc::html($file) . "</h3>";
            $out .= "<ul>";
            foreach ($errors as $error) {
                $out .= "<li>" . Enc::html($error) . "</li>";
            }
            $out .= "</ul>";
        }

        return $out;
    }


    public function getErrors()
    {
        return $this->errors;
    }
}
