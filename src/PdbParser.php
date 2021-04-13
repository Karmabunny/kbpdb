<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DOMDocument;
use Exception;
use karmabunny\kb\Enc;


/**
* Provides a system for syncing a database to a database definition.
*
* The database definition is stored in one or more XML files, which get merged
* together before the sync is done.
* Contains code that may be MySQL specific.
**/
class PdbParser
{
    /** @var Pdb */
    private $pdb;

    public $tables;
    private $views;
    private $default_attrs;
    private $load_errors;


    /**
     * Initial loading and set-up
     *
     * @param Pdb|PdbConfig|array $config
     * @param bool $act True if queries should be run, false if they shouldn't (i.e. a dry-run)
     **/
    public function __construct($config, $act)
    {
        if ($config instanceof Pdb) {
            $this->pdb = $config;
        }
        else {
            $this->pdb = new Pdb($config);
        }

        $this->database = $this->pdb->config->database;
        $this->prefix = $this->pdb->config->prefix;
        $this->act = $act;

        $this->default_attrs = [
            'table' => ['engine' => 'InnoDB', 'charset' => 'utf8', 'collate' => 'utf8_unicode_ci'],
            'column' => ['allownull' => 1],
            'index' => ['type' => 'index']
        ];

        $this->tables = [];
        $this->views = [];
        $this->load_errors = [];
    }


    /**
    * Loads the XML definition file
    *
    * @param string|DOMDocument $file DOMDocument or filename to load.
    **/
    public function loadXml($dom)
    {
        // If its not a DOMDocument, assume a filename
        if ($dom instanceof DOMDocument) {
            $filename = $dom->documentURI;
        } else {
            $filename = $dom;
            $tmp = new DOMDocument();
            $tmp->loadXML(file_get_contents($filename));
            if ($tmp == null) {
                $this->load_errors[$filename] = ['XML parse error'];
                return;
            }
            $dom = $tmp;
        }

        // Validate the XML file against the XSD schema
        libxml_use_internal_errors(true);
        $result = $dom->schemaValidateSource(file_get_contents(__DIR__ . '/db_struct.xsd'));
        if (! $result) {
            $this->load_errors[$filename] = [];

            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                switch ($error->level) {
                    case LIBXML_ERR_WARNING:
                        $err = "<b>Warning {$error->code}</b>: ";
                        break;
                    case LIBXML_ERR_ERROR:
                        $err = "<b>Error {$error->code}</b>: ";
                        break;
                    case LIBXML_ERR_FATAL:
                        $err = "<b>Fatal Error {$error->code}</b>: ";
                        break;
                }
                $err .= Enc::html(trim($error->message));
                $err .= " on line <b>{$error->line}</b>";
                $this->load_errors[$filename][] = $err;
            }

            libxml_clear_errors();
        }
        libxml_use_internal_errors(false);

        // Tables
        $table_nodes = $dom->getElementsByTagName('table');
        foreach ($table_nodes as $table_node) {
            if ($table_node->parentNode->tagName == 'defaults') continue;

            $this->mergeDefaultAttrs($table_node);

            if (! $table_node->hasAttribute('name')) throw new Exception ('A table exists in the xml without a defined name');
            $table_name = $table_node->getAttribute('name');

            // If the table doesn't exist yet in memory, create it
            // It may already exist if doing a cross-module db structure merge
            $is_new = false;
            if (empty($this->tables[$table_name])) {
                $this->tables[$table_name] = [];
                $this->tables[$table_name]['columns'] = [];
                $this->tables[$table_name]['primary_key'] = [];
                $this->tables[$table_name]['indexes'] = [];
                $this->tables[$table_name]['foreign_keys'] = [];
                $this->tables[$table_name]['default_records'] = [];
                $is_new = true;
            }

            // Columns
            $column_nodes = $table_node->getElementsByTagName('column');
            if ($column_nodes->length > 0) {
                foreach ($column_nodes as $node) {
                    $this->mergeDefaultAttrs($node);

                    $col_name = $node->getAttribute('name');

                    $type = PdbHelpers::typeToUpper($node->getAttribute('type'));
                    $this->tables[$table_name]['columns'][$col_name] = [
                        'name' => $col_name,
                        'type' => $type,
                        'allownull' => (int) $node->getAttribute('allownull'),
                        'autoinc' => (int) $node->getAttribute('autoinc'),
                        'default' => $node->getAttribute('default'),
                        'previous-names' => $node->getAttribute('previous-names'),
                    ];
                }
            }

            // Indexes
            $index_nodes = $table_node->getElementsByTagName('index');
            foreach ($index_nodes as $node) {
                $this->mergeDefaultAttrs($node);

                $index = [];

                $col_nodes = $node->getElementsByTagName('col');
                foreach ($col_nodes as $col) {
                    $this->mergeDefaultAttrs($col);
                    $col_name = $col->getAttribute('name');
                    $index[] = $col_name;
                }

                $index['type'] = $node->getAttribute('type');

                $this->tables[$table_name]['indexes'][] = $index;

                $fk_nodes = $node->getElementsByTagName('foreign-key');
                if ($fk_nodes->length == 1) {
                    $fk = [];
                    $fk['from_column'] = $index[0];
                    $fk['to_table'] = $fk_nodes->item(0)->getAttribute('table');
                    $fk['to_column'] = $fk_nodes->item(0)->getAttribute('column');
                    $fk['update'] = $fk_nodes->item(0)->getAttribute('update');
                    $fk['delete'] = $fk_nodes->item(0)->getAttribute('delete');
                    $this->tables[$table_name]['foreign_keys'][] = $fk;
                }
            }

            // Only columns and indexes can be merged across XML files.
            if (! $is_new) continue;

            // Attributes (engine, charset, etc)
            foreach ($table_node->attributes as $attr) {
                $this->tables[$table_name]['attrs'][$attr->name] = $attr->value;
            }

            // Primary key
            $primary_nodes = $table_node->getElementsByTagName('primary');
            if ($primary_nodes->length > 0) {
                $primary_nodes = $primary_nodes->item(0)->getElementsByTagName('col');
            }

            if ($primary_nodes) {
                foreach ($primary_nodes as $node) {
                    $this->mergeDefaultAttrs($node);

                    $col_name = $node->getAttribute('name');
                    $this->tables[$table_name]['primary_key'][] = $col_name;
                }
            }

            // Default records
            $default_nodes = $table_node->getElementsByTagName('default_records');
            foreach ($default_nodes as $node) {
                $this->mergeDefaultAttrs($node);

                $record_nodes = $node->getElementsByTagName('record');
                foreach ($record_nodes as $record) {
                    $record_attrs = [];
                    foreach ($record->attributes as $attr) {
                        $record_attrs[$attr->name] = $attr->value;
                    }
                    $this->tables[$table_name]['default_records'][] = $record_attrs;
                }
            }
        }

        // Views
        $views_nodes = $dom->getElementsByTagName('view');
        foreach ($views_nodes as $view_node) {
            if (! $table_node->hasAttribute('name')) throw new Exception ('A view exists in the xml without a defined name');
            $view_name = $view_node->getAttribute('name');

            $this->views[$view_name] = (string) $view_node->firstChild->data;
        }
    }


    /**
    * Run a sanity check over all loaded tables
    **/
    public function sanityCheck()
    {
        foreach ($this->tables as $table_name => $table_def) {
            $errs = $this->tableSanityCheck($table_name, $table_def);
            if (count($errs)) {
                $this->load_errors['table "' . $table_name . '"'] = $errs;
            }
        }
        $this->load_errors = array_filter($this->load_errors);
    }


    /**
    * Were there any load or sanity check errors?
    **/
    public function hasLoadErrors()
    {
        return (count($this->load_errors) > 0);
    }


    /**
    * Return HTML of the load or sanity check errors
    *
    * @return string HTML
    **/
    public function getLoadErrorsHtml()
    {
        $out = '';

        foreach ($this->load_errors as $file => $errors) {
            $out .= "<h3>Errors in " . Enc::html($file) . "</h3>";
            $out .= "<ul>";
            foreach ($errors as $error) {
                $out .= "<li>{$error}</li>";
            }
            $out .= "</ul>";
        }

        return $out;
    }



    /**
    * Do a sanity check on the table definition
    *
    * @param string $table_name The name of the table to check
    * @param array $table_def The table definition
    *
    * @return array Any errors which were detected
    **/
    private function tableSanityCheck($table_name, $table_def)
    {
        $errors = [];

        if (count($table_def['columns']) == 0) {
            $errors[] = 'No columns defined';
            return $errors;
        }

        // Check the id column is an int
        if (!empty($table_def['columns']['id'])) {
            $def = $table_def['columns']['id'];
            if (!preg_match('!^(BIG)?INT UNSIGNED$!i', $def['type'])) {
                $errors[] = 'Bad type for "id" column, use INT UNSIGNED or BIGINT UNSIGNED';
            }
            if ($def['autoinc'] == 1) {
                if (count($table_def['primary_key']) != 1 or $table_def['primary_key'][0] != 'id') {
                    $errors[] = 'Column is autoinc, but isn\'t only column in primary key';
                }
            }
        }

        // Check primary key exists and has columns
        if (empty($table_def['primary_key'])) {
            $errors[] = 'No primary key defined';
        } else {
            if (count($table_def['primary_key']) == 0) {
                $errors[] = 'Primary key doesn\'t have any columns';
            }
        }

        // Check types match on both sites of the reference
        // Check column is nullable if SET NULL is used
        // Check the TO side of the reference is indexed
        foreach ($table_def['foreign_keys'] as $fk) {
            $from_column = @$table_def['columns'][$fk['from_column']];
            $to_column = @$this->tables[$fk['to_table']]['columns'][$fk['to_column']];

            if (!$from_column) {
                $errors[] = "Foreign key \"{$fk['from_column']}\" on unknown column \"{$table_name}.{$fk['from_column']}\"";
                continue;
            }

            if (!$to_column) {
                $errors[] = "Foreign key \"{$fk['from_column']}\" points to unknown column \"{$fk['to_table']}.{$fk['to_column']}\"";
                continue;
            }

            if ($to_column['type'] != $from_column['type']) {
                $errors[] = 'Foreign key "' . $fk['from_column'] . '" column type mismatch (' . $to_column['type'] . ' vs ' . $from_column['type'] . ')';
            }

            if ($fk['update'] == 'set-null' and $table_def['columns'][$fk['from_column']]['allownull'] == 0) {
                $errors[] = 'Foreign key "' . $fk['from_column'] . '" update action to SET NULL but column doesn\'t allow nulls';
            }

            if ($fk['delete'] == 'set-null' and $table_def['columns'][$fk['from_column']]['allownull'] == 0) {
                $errors[] = 'Foreign key "' . $fk['from_column'] . '" delete action to SET NULL but column doesn\'t allow nulls';
            }

            $index_found = false;
            if (@$this->tables[$fk['to_table']]['primary_key'][0] == $fk['to_column']) {
                $index_found = true;
            } else {
                foreach ($this->tables[$fk['to_table']]['indexes'] as $index) {
                    if ($index[0] == $fk['to_column']) {
                        $index_found = true;
                        break;
                    }
                }
            }
            if (! $index_found) {
                $errors[] = 'Foreign key "' . $fk['from_column'] . '" referenced column (' . $fk['to_table'] . '.' . $fk['to_column'] . ') is not first column in an index';
            }
        }


        return $errors;
    }



    /**
    * Adds the default attrs to a node
    *
    * @param DOMElement $node The dom node to operate on
    **/
    private function mergeDefaultAttrs($node)
    {
        $default = $this->default_attrs[$node->tagName] ?? null;
        if (! is_array($default)) return;

        foreach ($default as $name => $value) {
            if (! $node->hasAttribute($name)) {
                $node->setAttribute($name, $value);
            }
        }
    }

}
