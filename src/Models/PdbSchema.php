<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use karmabunny\kb\Collection;
use karmabunny\pdb\PdbSchemaInterface;
use karmabunny\pdb\PdbStructWriterInterface;

/**
 * A schema that comes from the database or a `db_struct.xml` file.
 *
 * @package karmabunny\pdb
 */
class PdbSchema extends Collection implements PdbSchemaInterface, PdbStructWriterInterface
{

    /** @var string */
    public $name;

    /** @var PdbTable[] name => PdbTable */
    public $tables = [];

    /** @var PdbView[] name => PdbView */
    public $views = [];


    /** @inheritdoc */
    public function getTableNames(): array
    {
        return array_keys($this->tables);
    }


    /** @inheritdoc */
    public function getTables(): array
    {
        return $this->tables;
    }


    /** @inheritdoc */
    public function getViews(): array
    {
        return $this->views;
    }


    /**
     *
     * @param PdbSchemaInterface $schema
     * @return string[]
     */
    public function check(PdbSchemaInterface $schema): array
    {
        $errors = [];

        $tables = $schema->getTables();

        foreach ($this->getTables() as $table) {
            $checks = $table->check($tables);
            if (!$checks) continue;
            array_push($errors, ...$checks);
        }

        return $errors;
    }


    /** @inheritdoc */
    public function toXml(DOMDocument $doc): DOMNode
    {
        $schema = $doc->createElement('database');

        $schema->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        // TODO host the XSD someone public.
        // $schema->setAttribute('xsi:noNamespaceSchemaLocation', __DIR__ . '/db_struct.xsd');

        foreach ($this->tables as $table) {
            $node = $table->toXml($doc);
            $schema->appendChild($node);
        }

        foreach ($this->views as $view) {
            $node = $view->toXml($doc);
            $schema->appendChild($node);
        }

        return $schema;
    }


    /**
     * Write the schema to an XML string.
     *
     * @return string
     * @throws DOMException
     */
    public function writeXml(): string
    {
        $doc = new DOMDocument();
        $doc->formatOutput = true;

        $schema = $this->toXml($doc);
        $doc->appendChild($schema);

        return $doc->saveXML();
    }
}
