<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMNode;
use karmabunny\kb\Collection;
use karmabunny\pdb\PdbStructWriterInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbIndex extends Collection implements PdbStructWriterInterface
{

    const TYPE_INDEX = 'index';
    const TYPE_UNIQUE = 'unique';

    /** @var string */
    public $name;

    /** @var string */
    public $type = 'index';

    /** @var string[] */
    public $columns = [];


    /** @inheritdoc */
    public function toXml(DOMDocument $doc): DOMNode
    {
        $index = $doc->createElement('index');

        if ($this->type !== 'index') {
            $index->setAttribute('type', $this->type);
        }

        foreach ($this->columns as $column) {
            $node = $doc->createElement('col');
            $node->setAttribute('name', $column);
            $index->appendChild($node);
        }

        return $index;
    }

}
